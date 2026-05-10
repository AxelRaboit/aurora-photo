<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Service;

use Aurora\Core\Setting\Enum\ModuleParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;

final readonly class PhotoContext
{
    public function __construct(private SettingRepository $settingRepository) {}

    public function isAdminEnabled(): bool
    {
        return $this->settingRepository->getBoolean(ModuleParameterEnum::PhotoEnabled->value, true);
    }

    public function isFrontEnabled(): bool
    {
        return $this->settingRepository->getBoolean(ModuleParameterEnum::PhotoPublicEnabled->value, true);
    }

    public function isGalleriesEnabled(): bool
    {
        return $this->isAdminEnabled() && $this->settingRepository->getBoolean(ModuleParameterEnum::PhotoGalleriesEnabled->value, true);
    }
}
