<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Service;

use Aurora\Core\Module\Service\ModuleAccessChecker;
use Aurora\Core\Setting\Enum\ModuleParameterEnum;

final readonly class PhotoContext
{
    public function __construct(private ModuleAccessChecker $moduleAccessChecker) {}

    public function isAdminEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(ModuleParameterEnum::PhotoEnabled);
    }

    public function isFrontEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(ModuleParameterEnum::PhotoPublicEnabled);
    }

    public function isGalleriesEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(ModuleParameterEnum::PhotoGalleriesEnabled);
    }
}
