<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

use Aurora\Core\Support\Arr;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryItemBulkDeleteInput
{
    public function __construct(
        /**
         * @var list<int>
         */
        #[Assert\Count(min: 1, minMessage: 'photo.galleries.errors.items_required')]
        #[Assert\All([new Assert\Positive(message: 'photo.galleries.errors.invalid_item_id')])]
        public array $itemIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(itemIds: Arr::positiveInts($data['itemIds'] ?? null));
    }
}
