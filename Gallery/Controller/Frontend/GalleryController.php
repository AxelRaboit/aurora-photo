<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Frontend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Frontend\Controller\JsonRequestTrait;
use Aurora\Core\Frontend\Controller\JsonResponseTrait;
use Aurora\Module\Configuration\Theme\Service\ThemeResolver;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryInviteManagerInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\View\Frontend\GalleryViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public gallery page render + unlock + invite/shared link redemption.
 * Visitor mutation flows (pick, comment, finalize) live in
 * `GalleryPicksController`; downloads in `GalleryDownloadsController`.
 */
#[Route('/g/{slug}', name: 'frontend_gallery', requirements: ['slug' => '[a-z0-9-]+'])]
final class GalleryController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GalleryAccessService $accessService,
        private readonly GalleryInviteRepository $inviteRepository,
        private readonly GalleryInviteManagerInterface $inviteManager,
        private readonly GalleryViewBuilder $viewBuilder,
        private readonly ThemeResolver $themeResolver,
    ) {}

    #[Route('/i/{token}', name: '_invite_redeem', requirements: ['token' => '[a-f0-9]{48}'], methods: [HttpMethodEnum::Get->value])]
    public function redeemInvite(string $slug, string $token): Response
    {
        $gallery = $this->loadGallery($slug);
        $invite = $this->inviteRepository->findOneByToken($token);
        if (!$invite instanceof GalleryInviteInterface || $invite->getGallery()->getId() !== $gallery->getId()) {
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
        $gallery = $this->loadGallery(slug: $slug, allowExpired: true);
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
            return $this->render($this->themeResolver->resolve('photo/gallery/unlock'), $this->viewBuilder->unlockView($gallery));
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

    private function loadGallery(string $slug, bool $allowExpired = false): GalleryInterface
    {
        $gallery = $this->galleryRepository->findOneBySlug($slug);
        if (!$gallery instanceof GalleryInterface) {
            throw $this->createNotFoundException();
        }

        if (!$allowExpired && $gallery->isExpired()) {
            throw $this->createNotFoundException();
        }

        return $gallery;
    }

    private function renderGalleryView(GalleryInterface $gallery, string $visitorToken, bool $readOnly): Response
    {
        return $this->render($this->themeResolver->resolve('photo/gallery/index'), $this->viewBuilder->galleryView($gallery, $visitorToken, $readOnly));
    }
}
