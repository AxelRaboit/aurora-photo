<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Aurora\Core\Support\Str;
use DateTimeImmutable;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GalleryInputFactoryInterface::class)]
class GalleryInputFactory implements GalleryInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): GalleryInputInterface
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

        return new GalleryInput(
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
