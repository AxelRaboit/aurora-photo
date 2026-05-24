<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Frontend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryFinalizeInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemCommentInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryPickInput;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Exception\MaxPicksReachedException;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\Service\GalleryCommentService;
use Aurora\Module\Photo\Gallery\Service\GalleryNotificationService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Visitor pick/comment/finalize actions on a public gallery. Split from
 * `GalleryController` to keep the visitor session interactions (which
 * mutate gallery state via tokens) isolated from the page rendering.
 * Route names preserved (`frontend_gallery_pick`, `_comment`, `_finalize`).
 */
#[Route('/g/{slug}', name: 'frontend_gallery', requirements: ['slug' => '[a-z0-9-]+'])]
final class GalleryPicksController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GalleryItemRepository $itemRepository,
        private readonly GalleryAccessService $accessService,
        private readonly GalleryPickService $pickService,
        private readonly GalleryCommentService $commentService,
        private readonly GalleryNotificationService $notificationService,
        private readonly PayloadValidator $payloadValidator,
        private readonly GallerySerializerInterface $gallerySerializer,
    ) {}

    #[Route('/pick/{itemId}', name: '_pick', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    public function pick(string $slug, int $itemId, Request $request): JsonResponse
    {
        $gallery = $this->loadGallery($slug);
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return $this->jsonForbidden();
        }

        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItemInterface) {
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
        if (!$item instanceof GalleryItemInterface) {
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

    private function loadGallery(string $slug): GalleryInterface
    {
        $gallery = $this->galleryRepository->findOneBySlug($slug);
        if (!$gallery instanceof GalleryInterface || $gallery->isExpired()) {
            throw $this->createNotFoundException();
        }

        return $gallery;
    }
}
