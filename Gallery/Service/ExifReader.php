<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lightweight EXIF reader scoped to the photo module. Only reads the one
 * timestamp the burst-grouping feature needs — anything heavier (full metadata
 * indexing) belongs in the Media service.
 */
final readonly class ExifReader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads')]
        private string $uploadDir,
    ) {}

    public function readDateTimeOriginal(string $relativePath): ?DateTimeImmutable
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        $absolute = $this->uploadDir.'/'.$relativePath;
        if (!is_file($absolute)) {
            return null;
        }

        // exif_read_data emits warnings on non-EXIF files (PNG, WebP, malformed
        // JPEGs); we don't want them in the logs for routine uploads.
        $exif = @exif_read_data($absolute, 'EXIF', true);
        if (!is_array($exif)) {
            return null;
        }

        // Cameras can write the timestamp under multiple keys depending on the
        // file (DateTimeOriginal is the shoot moment; DateTime is when the file
        // was last modified). Prefer the original.
        $candidates = [
            $exif['EXIF']['DateTimeOriginal'] ?? null,
            $exif['EXIF']['DateTimeDigitized'] ?? null,
            $exif['IFD0']['DateTime'] ?? null,
            $exif['DateTimeOriginal'] ?? null,
            $exif['DateTime'] ?? null,
        ];

        foreach ($candidates as $raw) {
            if (!is_string($raw)) {
                continue;
            }

            if ('' === $raw) {
                continue;
            }

            // EXIF format is "Y:m:d H:i:s" — DateTimeImmutable doesn't parse it directly.
            $parsed = DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }
}
