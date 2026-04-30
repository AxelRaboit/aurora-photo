<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\DTO;

use Aurora\Core\Support\Str;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryItemCommentInput
{
    public const int MAX_LENGTH = 2000;

    public function __construct(
        #[Assert\NotBlank(message: 'photo.galleries.errors.comment_required')]
        #[Assert\Length(max: self::MAX_LENGTH, maxMessage: 'photo.galleries.errors.comment_too_long')]
        public string $content = '',
        public ?string $visitorName = null,
        #[Assert\Email(message: 'photo.galleries.errors.email_invalid')]
        public ?string $visitorEmail = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            content: Str::trimFromArray($data, 'content'),
            visitorName: Str::trimOrNullFromArray($data, 'name'),
            visitorEmail: Str::emailOrNullFromArray($data, 'email'),
        );
    }
}
