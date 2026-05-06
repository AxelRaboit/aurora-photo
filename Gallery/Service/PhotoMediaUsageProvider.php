<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Media\Contract\MediaUsageProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final readonly class PhotoMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(
        private Connection $connection,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function findUsages(int $mediaId): array
    {
        $usages = [];

        // Cover images.
        $covers = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM photo_galleries WHERE cover_media_id = :id',
            ['id' => $mediaId],
        );
        foreach ($covers as $row) {
            $usages[] = [
                'type' => 'gallery.cover',
                'label' => $row['title'],
                'detail' => $this->translator->trans('photo.galleries.fields.coverMedia'),
                'href' => $this->safeUrl('backend_galleries_edit', ['id' => (int) $row['id']]),
            ];
        }

        // Items inside galleries (one entry per gallery, with the photo count).
        $items = $this->connection->fetchAllAssociative(
            'SELECT g.id, g.title, COUNT(i.id) AS cnt
             FROM photo_gallery_items i
             INNER JOIN photo_galleries g ON g.id = i.gallery_id
             WHERE i.media_id = :id
             GROUP BY g.id, g.title',
            ['id' => $mediaId],
        );
        foreach ($items as $row) {
            $usages[] = [
                'type' => 'gallery.item',
                'label' => $row['title'],
                'detail' => $this->translator->trans('photo.galleries.usage.itemCount', ['count' => (int) $row['cnt']]),
                'href' => $this->safeUrl('backend_galleries_edit', ['id' => (int) $row['id']]),
            ];
        }

        return $usages;
    }

    /** @param array<string, mixed> $params */
    private function safeUrl(string $route, array $params): ?string
    {
        try {
            return $this->urlGenerator->generate($route, $params);
        } catch (Throwable) {
            return null;
        }
    }
}
