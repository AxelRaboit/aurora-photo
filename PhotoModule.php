<?php

declare(strict_types=1);

namespace Aurora\Module\Photo;

use Aurora\Core\Module\ModuleInterface;
use Aurora\Core\Module\NavItem;
use Aurora\Core\Module\NavPermission;
use Aurora\Core\Module\NavSection;
use Aurora\Module\Photo\Service\PhotoContext;

final readonly class PhotoModule implements ModuleInterface
{
    public function __construct(private PhotoContext $photoContext) {}

    public function getId(): string
    {
        return 'photo';
    }

    public function getPermissions(): array
    {
        return [
            new NavPermission('photo.galleries.view'),
            new NavPermission('photo.galleries.create'),
            new NavPermission('photo.galleries.edit'),
            new NavPermission('photo.galleries.delete'),
        ];
    }

    public function getNavSections(): array
    {
        if (!$this->photoContext->isAdminEnabled()) {
            return [];
        }

        return [
            new NavSection('photo', [
                new NavItem('admin_galleries', 'admin.nav.galleries', 'images'),
            ], priority: 70),
        ];
    }
}
