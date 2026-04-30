<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Enum;

/**
 * Buckets a visitor can sort photos into. Each is independent — picking the
 * same photo as Favorite AND Print creates two distinct GalleryPick rows.
 *
 * - Favorite: the default "I want this one" selection.
 * - Print:    photos the client wants to see printed (e.g. for an album).
 * - Discard:  photos the client explicitly wants removed before delivery.
 */
enum PickKindEnum: string
{
    case Favorite = 'favorite';
    case Print = 'print';
    case Discard = 'discard';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
