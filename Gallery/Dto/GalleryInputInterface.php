<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use DateTimeImmutable;

interface GalleryInputInterface
{
    public function getTitle(): string;

    public function getSlug(): string;

    public function getDescription(): ?string;

    /** Plain password — null means no change. */
    public function getPassword(): ?string;

    public function shouldClearPassword(): bool;

    public function getCoverMediaId(): ?int;

    public function getExpiresAt(): ?DateTimeImmutable;

    public function isAllowOriginals(): bool;

    public function isAllowZipDownload(): bool;

    public function isPicksRequireIdentity(): bool;

    public function getMaxPicks(): ?int;

    public function isAllowVisitorComments(): bool;

    public function isWatermarkEnabled(): bool;

    public function getWatermarkText(): ?string;

    public function getClientContactId(): ?int;
}
