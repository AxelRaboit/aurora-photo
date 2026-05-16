<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Frontend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\Service\GalleryDownloadService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Visitor download endpoints — single item or full ZIP. Split from
 * `GalleryController`. Route names preserved (`frontend_gallery_download_item`,
 * `_download_zip`).
 */
#[Route('/g/{slug}', name: 'frontend_gallery', requirements: ['slug' => '[a-z0-9-]+'])]
final class GalleryDownloadsController extends AbstractController
{
    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GalleryItemRepository $itemRepository,
        private readonly GalleryAccessService $accessService,
        private readonly GalleryPickService $pickService,
        private readonly GalleryDownloadService $downloadService,
    ) {}

    #[Route('/download/{itemId}', name: '_download_item', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Get->value])]
    public function downloadItem(string $slug, int $itemId, Request $request): BinaryFileResponse
    {
        $gallery = $this->loadGallery($slug);
        if (!$this->accessService->isUnlocked($request, $gallery)) {
            throw $this->createNotFoundException();
        }

        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItemInterface) {
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
        $gallery = $this->loadGallery($slug);
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

    /**
     * Downloads allow expired galleries (cookie still valid → preview-only
     * fallback) so the gallery may be flagged `isExpired()` but still resolvable.
     */
    private function loadGallery(string $slug): GalleryInterface
    {
        $gallery = $this->galleryRepository->findOneBySlug($slug);
        if (!$gallery instanceof GalleryInterface) {
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
    private function resolveVisitorWatermark(Request $request, GalleryInterface $gallery): ?string
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

    private function visitorHasPicked(Request $request, GalleryInterface $gallery, GalleryItemInterface $item): bool
    {
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return false;
        }

        $picked = $this->pickService->itemsPickedBy($gallery, $token);

        return array_any($picked, fn ($pickedItem): bool => $pickedItem->getId() === $item->getId());
    }

    /**
     * @return list<GalleryItemInterface>
     */
    private function visitorPickedItems(GalleryInterface $gallery, Request $request): array
    {
        $token = $this->accessService->readVisitorToken($request, $gallery);
        if (null === $token) {
            return [];
        }

        return $this->pickService->itemsPickedBy($gallery, $token, PickKindEnum::Favorite);
    }
}
