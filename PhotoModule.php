<?php

declare(strict_types=1);

namespace Aurora\Module\Photo;

use Aurora\Core\Module\Contract\ModuleInterface;
use Aurora\Core\Module\Contract\ModuleToggleProviderInterface;
use Aurora\Core\Module\Nav\NavItem;
use Aurora\Core\Module\Nav\NavPermission;
use Aurora\Core\Module\Nav\NavSection;
use Aurora\Module\Configuration\Setting\Enum\ModuleParameterEnum;

final readonly class PhotoModule implements ModuleInterface, ModuleToggleProviderInterface
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
        if (!$this->photoContext->isBackendEnabled()) {
            return [];
        }

        $items = [];

        if ($this->photoContext->isGalleriesEnabled()) {
            $items[] = new NavItem('backend_photo_galleries', 'backend.nav.galleries', 'images', requiredPrivilege: 'photo.galleries.view', descriptionKey: 'backend.nav.galleries_description');
        }

        if ([] === $items) {
            return [];
        }

        return [new NavSection('photo', $items, priority: 70)];
    }

    public function getCatalogNavSections(): array
    {
        return [
            new NavSection('photo', [
                new NavItem('backend_photo_galleries', 'backend.nav.galleries', 'images', requiredPrivilege: 'photo.galleries.view', descriptionKey: 'backend.nav.galleries_description'),
            ], priority: 70),
        ];
    }

    public function getToggles(): array
    {
        return [
            ModuleParameterEnum::PhotoBackend->toToggle(),
            ModuleParameterEnum::PhotoFrontend->toToggle(),
            ModuleParameterEnum::PhotoGalleries->toToggle(),
        ];
    }
}
