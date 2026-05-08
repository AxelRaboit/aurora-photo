<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryItemAddInput
{
    public function __construct(
        /**
         * @var list<int>
         */
        #[Assert\Count(min: 1, minMessage: 'photo.galleries.errors.items_required')]
        #[Assert\All([new Assert\Positive(message: 'photo.galleries.errors.invalid_media_id')])]
        public array $mediaIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $raw = (array) ($data['mediaIds'] ?? []);
        $ids = array_values(array_filter(array_map(intval(...), $raw), static fn (int $id): bool => $id > 0));

        return new self(mediaIds: $ids);
    }
}
