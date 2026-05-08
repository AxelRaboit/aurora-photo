<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Aurora\Core\Support\Str;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'photo.galleries.errors.title_required')]
        #[Assert\Length(max: 200)]
        public string $title = '',
        #[Assert\NotBlank(message: 'photo.galleries.errors.slug_required')]
        #[Assert\Length(max: 80)]
        #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'photo.galleries.errors.slug_format')]
        public string $slug = '',
        public ?string $description = null,
        /** Plain password — null/empty means no password change; '' explicit clear handled separately. */
        public ?string $password = null,
        public bool $clearPassword = false,
        #[Assert\Positive]
        public ?int $coverMediaId = null,
        public ?DateTimeImmutable $expiresAt = null,
        public bool $allowOriginals = true,
        public bool $allowZipDownload = true,
        public bool $picksRequireIdentity = false,
        #[Assert\Positive]
        public ?int $maxPicks = null,
        public bool $allowVisitorComments = false,
        public bool $watermarkEnabled = false,
        #[Assert\Length(max: 100)]
        public ?string $watermarkText = null,
        #[Assert\Positive]
        public ?int $clientContactId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $expiresRaw = $data['expiresAt'] ?? null;
        $expires = null;
        if (is_string($expiresRaw) && '' !== $expiresRaw) {
            try {
                $expires = new DateTimeImmutable($expiresRaw);
            } catch (Exception) {
                $expires = null;
            }
        }

        return new self(
            title: Str::trimFromArray($data, 'title'),
            slug: Str::trimFromArray($data, 'slug'),
            description: Str::trimOrNullFromArray($data, 'description'),
            password: array_key_exists('password', $data) ? (string) $data['password'] : null,
            clearPassword: (bool) ($data['clearPassword'] ?? false),
            coverMediaId: isset($data['coverMediaId']) && '' !== (string) $data['coverMediaId'] ? (int) $data['coverMediaId'] : null,
            expiresAt: $expires,
            allowOriginals: (bool) ($data['allowOriginals'] ?? true),
            allowZipDownload: (bool) ($data['allowZipDownload'] ?? true),
            picksRequireIdentity: (bool) ($data['picksRequireIdentity'] ?? false),
            maxPicks: isset($data['maxPicks']) && '' !== (string) $data['maxPicks'] ? (int) $data['maxPicks'] : null,
            allowVisitorComments: (bool) ($data['allowVisitorComments'] ?? false),
            watermarkEnabled: (bool) ($data['watermarkEnabled'] ?? false),
            watermarkText: Str::trimOrNullFromArray($data, 'watermarkText'),
            clientContactId: isset($data['clientContactId']) && '' !== (string) $data['clientContactId'] ? (int) $data['clientContactId'] : null,
        );
    }
}
