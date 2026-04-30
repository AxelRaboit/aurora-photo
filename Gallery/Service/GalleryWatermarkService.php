<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Module\Photo\Gallery\Entity\Gallery;
use GdImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies a text watermark on JPEG/PNG/WebP images and caches the result on
 * disk so subsequent downloads of the same item don't re-render the overlay.
 *
 * Cache layout:  uploads/photo-watermarks/<galleryId>/<basename>.<ext>
 * Cache key includes the gallery's text + a hash so changing the watermark
 * text invalidates entries automatically (the hash is part of the dir name).
 *
 * Originals are never watermarked — only the "web" variant served to the
 * client. The download service decides which path to feed in.
 */
class GalleryWatermarkService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private readonly string $uploadDir,
    ) {}

    /**
     * Applies the gallery's watermark (if any) on the source file. If
     * $visitorWatermark is provided, an additional traceable per-visitor mark
     * is tiled across the image — so a leaked screenshot can be tied back to
     * a specific client.
     *
     * Returns the absolute path to the watermarked cached version, or the
     * source itself if no watermark applies and no visitor watermark is set.
     */
    public function applyOrPassthrough(Gallery $gallery, string $absoluteSourcePath, ?string $visitorWatermark = null): string
    {
        $visitor = null !== $visitorWatermark ? mb_trim($visitorWatermark) : '';
        $hasGalleryMark = $gallery->hasActiveWatermark();
        $hasVisitorMark = '' !== $visitor;

        if ((!$hasGalleryMark && !$hasVisitorMark) || !is_file($absoluteSourcePath)) {
            return $absoluteSourcePath;
        }

        $cachedPath = $this->cachedPath($gallery, $absoluteSourcePath, $visitor);
        if (is_file($cachedPath) && filemtime($cachedPath) >= filemtime($absoluteSourcePath)) {
            return $cachedPath;
        }

        if (!is_dir(dirname($cachedPath))) {
            @mkdir(dirname($cachedPath), 0o775, true);
        }

        $rendered = $this->renderTo(
            $absoluteSourcePath,
            $hasGalleryMark ? ($gallery->getWatermarkText() ?? '') : '',
            $hasVisitorMark ? $visitor : '',
            $cachedPath,
        );

        return $rendered ? $cachedPath : $absoluteSourcePath;
    }

    /**
     * Wipes the on-disk cache for a gallery. Call after watermark settings
     * change so old renders aren't served.
     */
    public function clearCacheForGallery(Gallery $gallery): void
    {
        $dir = $this->galleryCacheDir($gallery);
        if (!is_dir($dir)) {
            return;
        }

        $this->rmDirRecursive($dir);
    }

    private function rmDirRecursive(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->rmDirRecursive($entry);
            } else {
                @unlink($entry);
            }
        }

        @rmdir($dir);
    }

    private function renderTo(string $sourcePath, string $galleryText, string $visitorText, string $destinationPath): bool
    {
        $info = @getimagesize($sourcePath);
        if (false === $info) {
            return false;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if (!$image instanceof GdImage) {
            return false;
        }

        if ('' !== $galleryText) {
            $this->drawCornerWatermark($image, $galleryText);
        }

        if ('' !== $visitorText) {
            $this->drawTiledVisitorWatermark($image, $visitorText);
        }

        $saved = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($image, $destinationPath, 85),
            IMAGETYPE_PNG => imagepng($image, $destinationPath, 6),
            IMAGETYPE_WEBP => imagewebp($image, $destinationPath, 85),
            default => false,
        };

        imagedestroy($image);

        return $saved;
    }

    private function drawCornerWatermark(GdImage $image, string $text): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $fontSize = max(8, min($width, $height) / 50);
        $padding = (int) ($fontSize * 1.5);

        $textColor = imagecolorallocatealpha($image, 255, 255, 255, 60);
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 80);

        $textWidth = imagefontwidth(5) * mb_strlen($text) * max(1, (int) ($fontSize / 12));
        $x = $width - $textWidth - $padding;
        $y = $height - $padding;

        imagestring($image, 5, $x + 1, $y + 1, $text, $shadowColor);
        imagestring($image, 5, $x, $y, $text, $textColor);
    }

    /**
     * Tiles the visitor identity across the image as a faint repeating mark.
     * Survives screenshots — the per-visitor cache shard means each client
     * downloads a uniquely stamped version.
     */
    private function drawTiledVisitorWatermark(GdImage $image, string $text): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        // GD's bitmap font 5 is ~9px wide × 15px tall. Compute step so we get
        // ~3 columns and ~5-6 rows on a typical landscape; the offset between
        // rows desyncs the pattern enough to look intentional.
        $textWidth = imagefontwidth(5) * mb_strlen($text);
        $textHeight = imagefontheight(5);
        $stepX = max($textWidth + 80, (int) ($width / 3));
        $stepY = max($textHeight + 60, (int) ($height / 7));

        $textColor = imagecolorallocatealpha($image, 255, 255, 255, 100);
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 110);

        $row = 0;
        for ($y = 20; $y < $height; $y += $stepY) {
            $offsetX = ($row % 2) * (int) ($stepX / 2);
            for ($x = -$stepX + $offsetX; $x < $width; $x += $stepX) {
                imagestring($image, 5, $x + 1, $y + 1, $text, $shadowColor);
                imagestring($image, 5, $x, $y, $text, $textColor);
            }

            ++$row;
        }
    }

    /**
     * Renders a heavily pixelated + blurred preview of the image at
     * $absoluteSourcePath. Used as a graceful fallback when the visitor isn't
     * entitled to the real photo (expired gallery, originals disabled,
     * pre-finalize attempt on a quota-gated gallery, etc.).
     *
     * Cached on disk under uploads/photo-degraded/. Returns the source path
     * untouched if rendering fails.
     */
    public function applyDegradation(string $absoluteSourcePath): string
    {
        if (!is_file($absoluteSourcePath)) {
            return $absoluteSourcePath;
        }

        $cachedPath = $this->degradedCachedPath($absoluteSourcePath);
        if (is_file($cachedPath) && filemtime($cachedPath) >= filemtime($absoluteSourcePath)) {
            return $cachedPath;
        }

        if (!is_dir(dirname($cachedPath))) {
            @mkdir(dirname($cachedPath), 0o775, true);
        }

        return $this->renderDegraded($absoluteSourcePath, $cachedPath) ? $cachedPath : $absoluteSourcePath;
    }

    private function renderDegraded(string $sourcePath, string $destinationPath): bool
    {
        $info = @getimagesize($sourcePath);
        if (false === $info) {
            return false;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if (!$image instanceof GdImage) {
            return false;
        }

        // Pixelate at a block size proportional to the image — keeps the look
        // consistent on both small thumbs and large originals.
        $block = max(8, (int) (min(imagesx($image), imagesy($image)) / 40));
        @imagefilter($image, IMG_FILTER_PIXELATE, $block, true);
        // Stack a gaussian blur on top to wipe edge details that pixelation alone leaves visible.
        @imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        // Slight desaturation hints at "preview" without being too harsh.
        @imagefilter($image, IMG_FILTER_COLORIZE, 0, 0, 0, 60);

        $saved = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($image, $destinationPath, 60),
            IMAGETYPE_PNG => imagepng($image, $destinationPath, 6),
            IMAGETYPE_WEBP => imagewebp($image, $destinationPath, 60),
            default => false,
        };

        imagedestroy($image);

        return $saved;
    }

    private function degradedCachedPath(string $sourcePath): string
    {
        $hash = sha1($sourcePath);
        $shard = mb_substr($hash, 0, 2);
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';

        return sprintf('%s/photo-degraded/%s/%s.%s', $this->uploadDir, $shard, $hash, $ext);
    }

    private function cachedPath(Gallery $gallery, string $sourcePath, string $visitorWatermark = ''): string
    {
        $visitorShard = '' === $visitorWatermark ? '_anon' : mb_substr(sha1($visitorWatermark), 0, 8);

        return sprintf('%s/%s/%s', $this->galleryCacheDir($gallery), $visitorShard, basename($sourcePath));
    }

    private function galleryCacheDir(Gallery $gallery): string
    {
        $hash = mb_substr(sha1((string) $gallery->getWatermarkText()), 0, 8);

        return sprintf('%s/photo-watermarks/%d-%s', $this->uploadDir, $gallery->getId(), $hash);
    }
}
