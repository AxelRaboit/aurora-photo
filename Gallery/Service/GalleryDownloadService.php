<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Media\Entity\Media;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

/**
 * Builds download responses for gallery items. Honours per-gallery flags:
 *  - allowOriginals: rejects "original" variant when false
 *  - allowZipDownload: bulk endpoint should 404 before reaching this service
 *
 * Bulk archive uses ZipStream (zero-memory streaming) — no temp file, suitable
 * for galleries with thousands of originals.
 */
final readonly class GalleryDownloadService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadDir,
        private GalleryWatermarkService $watermarkService,
    ) {}

    public function singleItemResponse(
        Gallery $gallery,
        GalleryItem $item,
        string $variant,
        bool $degraded = false,
        ?string $visitorWatermark = null,
    ): BinaryFileResponse {
        $path = $this->resolvePath($gallery, $item->getMedia(), $variant);
        if ($degraded) {
            $path = $this->watermarkService->applyDegradation($path);
        } elseif ('web' === $variant) {
            $path = $this->watermarkService->applyOrPassthrough($gallery, $path, $visitorWatermark);
        }

        $downloadName = $this->niceName($item->getMedia(), $variant, $degraded);

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    /**
     * Streams a ZIP archive to the client without buffering the full content
     * in memory or on disk. Items are read sequentially from the upload dir
     * and pushed through the response output stream.
     *
     * @param iterable<GalleryItem> $items
     */
    public function bulkZipResponse(
        Gallery $gallery,
        iterable $items,
        string $variant,
        bool $degraded = false,
        ?string $visitorWatermark = null,
    ): StreamedResponse {
        $archiveName = sprintf('%s.zip', $gallery->getSlug());

        $response = new StreamedResponse(function () use ($gallery, $items, $variant, $archiveName, $degraded, $visitorWatermark): void {
            $zip = new ZipStream(
                defaultEnableZeroHeader: true,
                sendHttpHeaders: false,
                outputName: $archiveName,
            );

            $usedNames = [];
            foreach ($items as $item) {
                $path = $this->resolvePath($gallery, $item->getMedia(), $variant);
                if ($degraded) {
                    $path = $this->watermarkService->applyDegradation($path);
                } elseif ('web' === $variant) {
                    $path = $this->watermarkService->applyOrPassthrough($gallery, $path, $visitorWatermark);
                }

                if (!is_file($path)) {
                    continue;
                }

                $name = $this->niceName($item->getMedia(), $variant, $degraded);
                $finalName = $this->uniqueName($name, $usedNames);
                $usedNames[$finalName] = true;

                $stream = fopen($path, 'rb');
                if (false === $stream) {
                    continue;
                }

                $zip->addFileFromStream($finalName, $stream);
                fclose($stream);
            }

            $zip->finish();
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $archiveName));
        // Disable nginx buffering so the client sees bytes flow as they're produced.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function resolvePath(Gallery $gallery, Media $media, string $variant): string
    {
        if ('original' === $variant) {
            if (!$gallery->isAllowOriginals()) {
                throw new DomainException('Original downloads are disabled for this gallery.');
            }

            return Path::join($this->uploadDir, $media->getPath());
        }

        // 'web' = the largest cached derivative, falling back to the original
        // when no medium variant has been generated yet.
        $variantPath = $media->getVariants()['large'] ?? $media->getVariants()['medium'] ?? null;
        $relative = $variantPath ?? $media->getPath();

        return Path::join($this->uploadDir, $relative);
    }

    private function niceName(Media $media, string $variant, bool $degraded = false): string
    {
        $base = pathinfo($media->getOriginalName(), PATHINFO_FILENAME) ?: pathinfo($media->getPath(), PATHINFO_FILENAME);
        $ext = pathinfo($media->getPath(), PATHINFO_EXTENSION) ?: 'jpg';
        $suffix = $degraded ? '-preview' : ('web' === $variant ? '-web' : '');

        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $base.$suffix).'.'.$ext;
    }

    /** @param array<string, true> $taken */
    private function uniqueName(string $candidate, array $taken): string
    {
        if (!isset($taken[$candidate])) {
            return $candidate;
        }

        $info = pathinfo($candidate);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';

        $i = 1;
        do {
            $next = sprintf('%s-%d%s', $base, $i++, $ext);
        } while (isset($taken[$next]));

        return $next;
    }
}
