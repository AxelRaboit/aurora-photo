<?php

declare(strict_types=1);

namespace Aurora\Module\Photo;

use Aurora\Core\Frontend\Contract\FrontendInterface;
use Aurora\Module\Configuration\Setting\Enum\ModuleParameterEnum;

final class PhotoFrontendDescriptor implements FrontendInterface
{
    public function getSlug(): string
    {
        return 'photo';
    }

    public function getLabel(): string
    {
        return 'Photo';
    }

    public function getHomeRoute(): string
    {
        return 'frontend_gallery';
    }

    public function getPriority(): int
    {
        return 3;
    }

    public function getModuleSettingKey(): string
    {
        return ModuleParameterEnum::PhotoFrontend->value;
    }

    public function getRoutePrefixes(): array
    {
        return ['frontend_gallery'];
    }
}
