<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;

interface GalleryItemManagerInterface
{
    /**
     * Adds the given media to the gallery (skipping already-attached media).
     *
     * @param list<int> $mediaIds
     *
     * @return int number of items effectively added
     */
    public function addItems(GalleryInterface $gallery, array $mediaIds): int;

    /**
     * Reorders the gallery's items in the order of the provided ids.
     * Ids that don't belong to the gallery are silently ignored.
     *
     * @param list<int> $orderedItemIds
     */
    public function reorder(GalleryInterface $gallery, array $orderedItemIds): void;

    public function updateCaption(GalleryItemInterface $item, ?string $caption): void;

    public function delete(GalleryItemInterface $item): void;

    /**
     * @param list<int> $itemIds
     *
     * @return int number of items effectively deleted
     */
    public function bulkDelete(GalleryInterface $gallery, array $itemIds): int;
}
