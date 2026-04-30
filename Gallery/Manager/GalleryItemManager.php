<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Core\Audit\Service\AuditLogger;
use Aurora\Core\Media\Repository\MediaRepository;
use Aurora\Module\Photo\Gallery\Contract\GalleryItemManagerInterface;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Service\ExifReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GalleryItemManagerInterface::class)]
final readonly class GalleryItemManager implements GalleryItemManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryItemRepository $itemRepository,
        private MediaRepository $mediaRepository,
        private AuditLogger $auditLogger,
        private ExifReader $exifReader,
    ) {}

    public function addItems(Gallery $gallery, array $mediaIds): int
    {
        $mediaIds = array_values(array_filter($mediaIds, static fn (int $id): bool => $id > 0));
        if ([] === $mediaIds) {
            return 0;
        }

        $existing = [];
        foreach ($gallery->getItems() as $item) {
            $existing[$item->getMedia()->getId()] = true;
        }

        $position = $this->itemRepository->nextPositionForGallery((int) $gallery->getId());
        $number = $this->itemRepository->nextNumberForGallery((int) $gallery->getId());
        $added = 0;
        foreach ($mediaIds as $mediaId) {
            if (isset($existing[$mediaId])) {
                continue;
            }

            $media = $this->mediaRepository->find($mediaId);
            if (null === $media) {
                continue;
            }

            $item = new GalleryItem();
            $item->setGallery($gallery);
            $item->setMedia($media);
            $item->setPosition($position++);
            $item->setNumber($number++);
            $item->setTakenAt($this->exifReader->readDateTimeOriginal($media->getPath()));
            $this->entityManager->persist($item);
            // Keep the in-memory collection in sync so callers can serialize
            // the gallery without re-fetching after the flush.
            $gallery->getItems()->add($item);
            ++$added;
        }

        if ($added > 0) {
            $this->entityManager->flush();
            $this->auditLogger->log('photo', 'gallery.items.added', 'Gallery', $gallery->getId(), ['count' => $added]);
        }

        return $added;
    }

    public function reorder(Gallery $gallery, array $orderedItemIds): void
    {
        $orderedItemIds = array_values(array_filter($orderedItemIds, static fn (int $id): bool => $id > 0));
        if ([] === $orderedItemIds) {
            return;
        }

        $position = 0;
        foreach ($orderedItemIds as $itemId) {
            $item = $this->itemRepository->find($itemId);
            if ($item instanceof GalleryItem && $item->getGallery()->getId() === $gallery->getId()) {
                $item->setPosition($position++);
            }
        }

        $this->entityManager->flush();
    }

    public function updateCaption(GalleryItem $item, ?string $caption): void
    {
        $caption = null !== $caption ? mb_trim($caption) : null;
        $item->setCaption('' !== $caption ? $caption : null);
        $this->entityManager->flush();
    }

    public function delete(GalleryItem $item): void
    {
        $galleryId = $item->getGallery()->getId();
        $itemId = $item->getId();

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.items.deleted', 'GalleryItem', $itemId, ['galleryId' => $galleryId]);
    }

    public function bulkDelete(Gallery $gallery, array $itemIds): int
    {
        $itemIds = array_values(array_filter($itemIds, static fn (int $id): bool => $id > 0));
        if ([] === $itemIds) {
            return 0;
        }

        $deleted = 0;
        foreach ($itemIds as $itemId) {
            $item = $this->itemRepository->find($itemId);
            if ($item instanceof GalleryItem && $item->getGallery()->getId() === $gallery->getId()) {
                $this->entityManager->remove($item);
                ++$deleted;
            }
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
            $this->auditLogger->log('photo', 'gallery.items.bulk_deleted', 'Gallery', $gallery->getId(), ['count' => $deleted]);
        }

        return $deleted;
    }
}
