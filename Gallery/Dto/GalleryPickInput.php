<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Aurora\Core\Support\Str;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryPickInput
{
    public function __construct(
        public ?string $visitorName = null,
        #[Assert\Email(message: 'photo.galleries.errors.email_invalid')]
        public ?string $visitorEmail = null,
        public PickKindEnum $kind = PickKindEnum::Favorite,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            visitorName: Str::trimOrNullFromArray($data, 'name'),
            visitorEmail: Str::emailOrNullFromArray($data, 'email'),
            kind: PickKindEnum::tryFrom((string) ($data['kind'] ?? '')) ?? PickKindEnum::Favorite,
        );
    }
}
