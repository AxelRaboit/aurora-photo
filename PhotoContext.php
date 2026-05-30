<?php

declare(strict_types=1);

namespace Aurora\Module\Photo;

use Aurora\Core\Module\Service\ModuleAccessChecker;
use Aurora\Module\Photo\Setting\PhotoModuleParameterEnum;

final readonly class PhotoContext
{
    public function __construct(private ModuleAccessChecker $moduleAccessChecker) {}

    public function isBackendEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(PhotoModuleParameterEnum::Backend->value);
    }

    public function isFrontEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(PhotoModuleParameterEnum::Frontend->value);
    }

    public function isGalleriesEnabled(): bool
    {
        return $this->moduleAccessChecker->isEnabled(PhotoModuleParameterEnum::Galleries->value);
    }
}
