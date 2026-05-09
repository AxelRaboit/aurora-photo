<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Service;

use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;

/**
 * Single source of truth for Photo module activation. Two independent toggles:
 *  - admin: hides the sidebar section and 404s /admin/galleries/*
 *  - front: 404s the public /g/{slug} pages without disabling the admin
 *
 * Useful pattern: shoot in admin while the public delivery is paused (e.g.
 * during the editing pipeline), then flip front on once everything is ready.
 */
final readonly class PhotoContext
{
    public function __construct(private SettingRepository $settingRepository) {}

    public function isAdminEnabled(): bool
    {
        return $this->settingRepository->getBoolean(ApplicationParameterEnum::PhotoEnabled->value, true);
    }

    public function isFrontEnabled(): bool
    {
        return $this->settingRepository->getBoolean(ApplicationParameterEnum::PhotoPublicEnabled->value, true);
    }
}
