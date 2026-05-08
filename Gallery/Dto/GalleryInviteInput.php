<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryInviteInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'photo.galleries.errors.name_required')]
        #[Assert\Length(max: 200)]
        public string $name = '',
        #[Assert\NotBlank(message: 'photo.galleries.errors.email_required')]
        #[Assert\Email(message: 'photo.galleries.errors.email_invalid')]
        public string $email = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: Str::trimFromArray($data, 'name'),
            email: Str::emailFromArray($data, 'email'),
        );
    }
}
