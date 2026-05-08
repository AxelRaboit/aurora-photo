<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class GalleryInput implements GalleryInputInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'photo.galleries.errors.title_required')]
        #[Assert\Length(max: 200)]
        public readonly string $title = '',
        #[Assert\NotBlank(message: 'photo.galleries.errors.slug_required')]
        #[Assert\Length(max: 80)]
        #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'photo.galleries.errors.slug_format')]
        public readonly string $slug = '',
        public readonly ?string $description = null,
        /** Plain password — null/empty means no password change; '' explicit clear handled separately. */
        public readonly ?string $password = null,
        public readonly bool $clearPassword = false,
        #[Assert\Positive]
        public readonly ?int $coverMediaId = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly bool $allowOriginals = true,
        public readonly bool $allowZipDownload = true,
        public readonly bool $picksRequireIdentity = false,
        #[Assert\Positive]
        public readonly ?int $maxPicks = null,
        public readonly bool $allowVisitorComments = false,
        public readonly bool $watermarkEnabled = false,
        #[Assert\Length(max: 100)]
        public readonly ?string $watermarkText = null,
        #[Assert\Positive]
        public readonly ?int $clientContactId = null,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function shouldClearPassword(): bool
    {
        return $this->clearPassword;
    }

    public function getCoverMediaId(): ?int
    {
        return $this->coverMediaId;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isAllowOriginals(): bool
    {
        return $this->allowOriginals;
    }

    public function isAllowZipDownload(): bool
    {
        return $this->allowZipDownload;
    }

    public function isPicksRequireIdentity(): bool
    {
        return $this->picksRequireIdentity;
    }

    public function getMaxPicks(): ?int
    {
        return $this->maxPicks;
    }

    public function isAllowVisitorComments(): bool
    {
        return $this->allowVisitorComments;
    }

    public function isWatermarkEnabled(): bool
    {
        return $this->watermarkEnabled;
    }

    public function getWatermarkText(): ?string
    {
        return $this->watermarkText;
    }

    public function getClientContactId(): ?int
    {
        return $this->clientContactId;
    }
}
