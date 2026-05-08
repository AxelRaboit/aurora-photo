<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Frontend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Frontend\Controller\JsonRequestTrait;
use Aurora\Core\Frontend\Controller\JsonResponseTrait;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryFinalizeInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemCommentInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryPickInput;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Module\Photo\Gallery\Exception\MaxPicksReachedException;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\Service\GalleryCommentService;
use Aurora\Module\Photo\Gallery\Service\GalleryDownloadService;
use Aurora\Module\Photo\Gallery\Service\GalleryInviteManager;
use Aurora\Module\Photo\Gallery\Service\GalleryNotificationService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Aurora\Module\Photo\Gallery\View\GalleryFrontViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/g/{slug}', name: 'frontend_gallery', requirements: ['slug' => '[a-z0-9-]+'])]
final class GalleryController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GalleryItemRepository $itemRepository,
        private readonly GalleryAccessService $accessService,
        private readonly GalleryPickService $pickService,
        private readonly GalleryCommentService $commentService,
        private readonly GalleryDownloadService $downloadService,
        private readonly GalleryNotificationService $notificationService,
        private readonly PayloadValidator $payloadValidator,
        private readonly GalleryInviteRepository $inviteRepository,
        private readonly GalleryInviteManager $inviteManager,
        private readonly GallerySerializerInterface $gallerySerializer,
        private readonly GalleryFrontViewBuilder $viewBuilder,
    ) {}

    #[Route('/i/{token}', name: '_invite_redeem', requirements: ['token' => '[a-f0-9]{48}'], methods: [HttpMethodEnum::Get->value])]
    public function redeemInvite(string $slug, string $token): Response
    {
        $gallery = $this->loadGallery($slug);
        $invite = $this->inviteRepository->findOneByToken($token);
        if (!$invite instanceof GalleryInvite || $invite->getGallery()->getId() !== $gallery->getId()) {
            throw $this->createNotFoundException();
        }

        $cookie = $this->accessService->unlockForInvite($invite);
        $this->inviteManager->markSeen($invite);

        $response = $this->redirectToRoute('frontend_gallery', ['slug' => $slug]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/shared/{visitorToken}/{signature}', name: '_shared', requirements: ['visitorToken' => '[a-f0-9]{32}', 'signature' => '[a-f0-9]{32}'], methods: [HttpMethodEnum::Get->value])]
    public function shared(string $slug, string $visitorToken, string $signature): Response
    {
        $gallery = $this->loadGallery(slugOrThrow: $slug, allowExpired: true);
        if (!$this->accessService->verifyShareSignature($gallery, $visitorToken, $signature)) {
            throw $this->createNotFoundException();
        }

        return $this->renderGalleryView($gallery, $visitorToken, readOnly: true);
    }

    #[Route('', name: '', methods: [HttpMethodEnum::Get->value])]
    public function show(string $slug, Request $request): Response
    {
        $gallery = $this->loadGallery($slug);

        [$token, $cookie] = $this->accessService->ensureVisitorToken($request, $gallery);

        if (null === $token) {
            return $this->render('@Photo/front/gallery/unlock.html.twig', $this->viewBuilder->unlockView($gallery));
        }

        $response = $this->renderGalleryView($gallery, $token, readOnly: false);
        if ($cookie instanceof Cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    #[Route('/unlock', name: '_unlock', methods: [HttpMethodEnum::Post->value])]
    public function unlock(string $slug, Request $request): Response
    {
        $gallery = $this->loadGallery($slug);
        $payload = $this->decodeJson($request);
        $password = isset($payload['password']) ? (string) $payload['password'] : null;

        $cookie = $this->accessService->unlock($gallery, $password);
        if (!$cookie instanceof Cookie) {
            return $this->jsonFailure('photo.frontend.unlock.invalid', HttpStatusEnum::Unauthorized->value);
        }

        $response = $this->jsonSuccess(['redirectUrl' => $this->generateUrl('frontend_gallery', ['slug' => $slug])]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/pick/{itemId}', name: '_pick', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    public function pick(string $slug, int $itemId, Request $request): JsonResponse
    {
        $gallery = $this->loadGallery($slug);
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return $this->jsonForbidden();
        }

        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItem) {
            return $this->jsonNotFound();
        }

        if ($gallery->isFinalized() || $this->pickService->isFinalizedBy($gallery, $token)) {
            return $this->jsonFailure('finalized', HttpStatusEnum::Conflict->value);
        }

        $input = GalleryPickInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        if ($gallery->isPicksRequireIdentity() && !$this->pickService->visitorHasIdentity($token, $input->visitorName, $input->visitorEmail)) {
            return $this->jsonFailure('identity_required', HttpStatusEnum::UnprocessableEntity->value);
        }

        try {
            $picked = $this->pickService->toggle($item, $token, $input->visitorName, $input->visitorEmail, $input->kind);
        } catch (MaxPicksReachedException $maxPicksReachedException) {
            return $this->jsonFailure('max_picks_reached', HttpStatusEnum::Conflict->value, ['limit' => $maxPicksReachedException->limit]);
        }

        return $this->jsonSuccess([
            'picked' => $picked,
            'kind' => $input->kind->value,
            'favoriteCount' => $this->pickService->favoriteCount($gallery, $token),
        ]);
    }

    #[Route('/comment/{itemId}', name: '_comment', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    public function comment(string $slug, int $itemId, Request $request): JsonResponse
    {
        $gallery = $this->loadGallery($slug);
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return $this->jsonForbidden();
        }

        if (!$gallery->isAllowVisitorComments()) {
            return $this->jsonForbidden();
        }

        if ($gallery->isFinalized() || $this->pickService->isFinalizedBy($gallery, $token)) {
            return $this->jsonFailure('finalized', HttpStatusEnum::Conflict->value);
        }

        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItem) {
            return $this->jsonNotFound();
        }

        $input = GalleryItemCommentInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        if ($gallery->isPicksRequireIdentity() && !$this->pickService->visitorHasIdentity($token, $input->visitorName, $input->visitorEmail)) {
            return $this->jsonFailure('identity_required', HttpStatusEnum::UnprocessableEntity->value);
        }

        // Auto-fill the comment author from prior picks when the visitor
        // didn't re-type their identity — so a single capture covers both flows.
        [$name, $email] = $this->pickService->recoverIdentity($token, $input->visitorName, $input->visitorEmail);
        $input = new GalleryItemCommentInput(
            content: $input->content,
            visitorName: $name,
            visitorEmail: $email,
        );

        $comment = $this->commentService->add($item, $token, $input);

        return $this->jsonSuccess(['comment' => $this->gallerySerializer->serializeComment($comment)]);
    }

    #[Route('/finalize', name: '_finalize', methods: [HttpMethodEnum::Post->value])]
    public function finalize(string $slug, Request $request): JsonResponse
    {
        $gallery = $this->loadGallery($slug);
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return $this->jsonForbidden();
        }

        if ($gallery->isFinalized()) {
            return $this->jsonFailure('finalized', HttpStatusEnum::Conflict->value);
        }

        if ($this->pickService->isFinalizedBy($gallery, $token)) {
            return $this->jsonSuccess(['alreadyFinalized' => true]);
        }

        $input = GalleryFinalizeInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        [$name, $email] = $this->pickService->recoverIdentity($token, $input->name, $input->email);

        $this->pickService->finalize($gallery, $token, $name, $email);
        $this->notificationService->notifyFinalized($gallery, $token, $name, $email);
        if (null !== $email) {
            $this->notificationService->notifyVisitor($gallery, $name, $email);
        }

        return $this->jsonSuccess();
    }

    #[Route('/download/{itemId}', name: '_download_item', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Get->value])]
    public function downloadItem(string $slug, int $itemId, Request $request): BinaryFileResponse
    {
        $gallery = $this->loadGallery(slugOrThrow: $slug, allowExpired: true);
        if (!$this->accessService->isUnlocked($request, $gallery)) {
            throw $this->createNotFoundException();
        }

        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItem) {
            throw $this->createNotFoundException();
        }

        $askedOriginal = 'original' === $request->query->get('variant');
        $degraded = false;

        // Scenario B: gallery expired but cookie still valid → preview-only
        if ($gallery->isExpired()) {
            $degraded = true;
        }

        // Scenario C: original requested but disabled by the photographer → preview-only
        if ($askedOriginal && !$gallery->isAllowOriginals()) {
            $degraded = true;
        }

        // Scenario A: gallery is quota-gated and the visitor didn't pick this
        // photo as a Favorite — they must commit picks to download in clear.
        if (null !== $gallery->getMaxPicks() && !$this->visitorHasPicked($request, $gallery, $item)) {
            $degraded = true;
        }

        $variant = $askedOriginal && $gallery->isAllowOriginals() && !$degraded ? 'original' : 'web';

        return $this->downloadService->singleItemResponse(
            $gallery,
            $item,
            $variant,
            $degraded,
            $this->resolveVisitorWatermark($request, $gallery),
        );
    }

    #[Route('/download.zip', name: '_download_zip', methods: [HttpMethodEnum::Get->value])]
    public function downloadZip(string $slug, Request $request): StreamedResponse
    {
        $gallery = $this->loadGallery(slugOrThrow: $slug, allowExpired: true);
        if (!$this->accessService->isUnlocked($request, $gallery) || !$gallery->isAllowZipDownload()) {
            throw $this->createNotFoundException();
        }

        $askedOriginal = 'original' === $request->query->get('variant');
        $picksOnly = '1' === $request->query->get('picks');

        $degraded = $gallery->isExpired() || ($askedOriginal && !$gallery->isAllowOriginals());

        $variant = $askedOriginal && $gallery->isAllowOriginals() && !$degraded ? 'original' : 'web';

        $items = $picksOnly ? $this->visitorPickedItems($gallery, $request) : $gallery->getItems();

        return $this->downloadService->bulkZipResponse(
            $gallery,
            $items,
            $variant,
            $degraded,
            $this->resolveVisitorWatermark($request, $gallery),
        );
    }

    private function loadGallery(string $slugOrThrow, bool $allowExpired = false): Gallery
    {
        $gallery = $this->galleryRepository->findOneBySlug($slugOrThrow);
        if (!$gallery instanceof Gallery) {
            throw $this->createNotFoundException();
        }

        if (!$allowExpired && $gallery->isExpired()) {
            throw $this->createNotFoundException();
        }

        return $gallery;
    }

    /**
     * Builds a traceable per-visitor watermark string used by the watermark
     * service to stamp downloaded images. Falls back to the visitor token
     * (always available once unlocked) so anonymous visitors still get a
     * unique stamp tied to their cookie — useful if a screenshot leaks.
     */
    private function resolveVisitorWatermark(Request $request, Gallery $gallery): ?string
    {
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return null;
        }

        [$name, $email] = $this->pickService->recoverIdentity($token, null, null);
        $parts = array_filter([$name, $email], static fn (?string $part): bool => null !== $part && '' !== $part);
        if ([] !== $parts) {
            return implode(' · ', $parts);
        }

        // Anonymous visitor: stamp a short token fingerprint so different
        // browsers / devices still produce distinct watermarked downloads.
        return 'ID '.mb_substr($token, 0, 8);
    }

    private function visitorHasPicked(Request $request, Gallery $gallery, GalleryItem $item): bool
    {
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return false;
        }

        $picked = $this->pickService->itemsPickedBy($gallery, $token);

        return array_any($picked, fn ($pickedItem): bool => $pickedItem->getId() === $item->getId());
    }

    private function renderGalleryView(Gallery $gallery, string $visitorToken, bool $readOnly): Response
    {
        return $this->render('@Photo/front/gallery/index.html.twig', $this->viewBuilder->galleryView($gallery, $visitorToken, $readOnly));
    }

    /**
     * @return list<GalleryItemInterface>
     */
    private function visitorPickedItems(Gallery $gallery, Request $request): array
    {
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return [];
        }

        return $this->pickService->itemsPickedBy($gallery, $token, PickKindEnum::Favorite);
    }
}
