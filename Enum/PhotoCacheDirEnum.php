<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Enum;

enum PhotoCacheDirEnum: string
{
    case Watermarks = 'watermarks';
    case Degraded   = 'degraded';
}
