<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Dashboard;

use Aurora\Core\Dashboard\DashboardStatsProviderInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;

/**
 * Photo slice of the backend dashboard. Lives in the Photo module so the
 * General dashboard never imports Photo repositories.
 */
final readonly class PhotoStatsProvider implements DashboardStatsProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
        private GalleryItemRepository $galleryItemRepository,
    ) {}

    public function getModuleKey(): string
    {
        return 'photo';
    }

    public function getStats(): array
    {
        return [
            'photo' => [
                'galleries' => $this->galleryRepository->count([]),
                'active' => $this->galleryRepository->countActive(),
                'finalized' => $this->galleryRepository->countFinalized(),
                'photos' => $this->galleryItemRepository->count([]),
            ],
        ];
    }
}
