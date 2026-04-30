<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\DTO;

use Aurora\Core\Support\Str;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryItemCaptionInput
{
    public const int MAX_LENGTH = 500;

    public function __construct(
        #[Assert\Length(max: self::MAX_LENGTH, maxMessage: 'photo.galleries.errors.caption_too_long')]
        public ?string $caption = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(caption: Str::trimOrNullFromArray($data, 'caption'));
    }
}
