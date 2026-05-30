<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Setting;

use Aurora\Module\Configuration\Setting\Provider\ApplicationParameterProviderInterface;

/**
 * Registers the Photo module's toggle settings with the
 * `aurora:application-parameter` sync command. Required so the Photo toggle
 * rows in core_settings are not flagged obsolete (and wiped) once Photo owns
 * its toggles instead of the central ModuleParameterEnum.
 */
final readonly class PhotoModuleParameterProvider implements ApplicationParameterProviderInterface
{
    public function getParameters(): iterable
    {
        yield from PhotoModuleParameterEnum::cases();
    }
}
