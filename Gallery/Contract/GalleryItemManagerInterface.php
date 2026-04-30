<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Contract;

use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;

interface GalleryItemManagerInterface
{
    /**
     * Adds the given media to the gallery (skipping already-attached media).
     *
     * @param list<int> $mediaIds
     *
     * @return int number of items effectively added
     */
    public function addItems(Gallery $gallery, array $mediaIds): int;

    /**
     * Reorders the gallery's items in the order of the provided ids.
     * Ids that don't belong to the gallery are silently ignored.
     *
     * @param list<int> $orderedItemIds
     */
    public function reorder(Gallery $gallery, array $orderedItemIds): void;

    public function updateCaption(GalleryItem $item, ?string $caption): void;

    public function delete(GalleryItem $item): void;

    /**
     * @param list<int> $itemIds
     *
     * @return int number of items effectively deleted
     */
    public function bulkDelete(Gallery $gallery, array $itemIds): int;
}
