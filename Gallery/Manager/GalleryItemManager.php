<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Core\Audit\Service\AuditLogger;
use Aurora\Core\Media\Repository\MediaRepository;
use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Service\ExifReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GalleryItemManagerInterface::class)]
class GalleryItemManager implements GalleryItemManagerInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly GalleryItemRepository $itemRepository,
        protected readonly MediaRepository $mediaRepository,
        protected readonly AuditLogger $auditLogger,
        protected readonly ExifReader $exifReader,
        protected readonly SequenceGenerator $sequenceGenerator,
        protected readonly SettingRepository $settingRepository,
    ) {}

    public function addItems(GalleryInterface $gallery, array $mediaIds): int
    {
        $mediaIds = array_values(array_filter($mediaIds, static fn (int $id): bool => $id > 0));
        if ([] === $mediaIds) {
            return 0;
        }

        $existing = [];
        foreach ($gallery->getItems() as $item) {
            $existing[$item->getMedia()->getId()] = true;
        }

        $prefix = $this->settingRepository->get(ApplicationParameterEnum::PhotoGalleryItemPrefix->value, SequencePrefixEnum::GalleryItem->value) ?? SequencePrefixEnum::GalleryItem->value;
        $position = $this->itemRepository->nextPositionForGallery((int) $gallery->getId());
        $number = $this->itemRepository->nextNumberForGallery((int) $gallery->getId());
        $added = 0;

        // Batch-fetch only the media we don't already have on the gallery.
        $newMediaIds = array_values(array_filter($mediaIds, static fn (int $id): bool => !isset($existing[$id])));
        $mediaById = [];
        if ([] !== $newMediaIds) {
            foreach ($this->mediaRepository->findBy(['id' => $newMediaIds]) as $media) {
                $mediaById[(int) $media->getId()] = $media;
            }
        }

        foreach ($mediaIds as $mediaId) {
            if (isset($existing[$mediaId])) {
                continue;
            }

            $media = $mediaById[$mediaId] ?? null;
            if (null === $media) {
                continue;
            }

            $item = $this->createGalleryItem();
            $item->setGallery($gallery);
            $item->setMedia($media);
            $item->setPosition($position++);
            $item->setNumber($number++);
            $item->setTakenAt($this->exifReader->readDateTimeOriginal($media->getPath()));
            $item->setReference($this->sequenceGenerator->next($prefix));
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

    public function reorder(GalleryInterface $gallery, array $orderedItemIds): void
    {
        $orderedItemIds = array_values(array_filter($orderedItemIds, static fn (int $id): bool => $id > 0));
        if ([] === $orderedItemIds) {
            return;
        }

        // Single batched query — index by id so the reorder loop is O(n).
        $itemById = [];
        foreach ($this->itemRepository->findBy(['id' => $orderedItemIds]) as $item) {
            $itemById[(int) $item->getId()] = $item;
        }

        $position = 0;
        foreach ($orderedItemIds as $itemId) {
            $item = $itemById[$itemId] ?? null;
            if ($item instanceof GalleryItemInterface && $item->getGallery()->getId() === $gallery->getId()) {
                $item->setPosition($position++);
            }
        }

        $this->entityManager->flush();
    }

    public function updateCaption(GalleryItemInterface $item, ?string $caption): void
    {
        $caption = null !== $caption ? mb_trim($caption) : null;
        $item->setCaption('' !== $caption ? $caption : null);
        $this->entityManager->flush();
    }

    public function delete(GalleryItemInterface $item): void
    {
        $galleryId = $item->getGallery()->getId();
        $itemId = $item->getId();

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.items.deleted', 'GalleryItem', $itemId, ['galleryId' => $galleryId]);
    }

    public function bulkDelete(GalleryInterface $gallery, array $itemIds): int
    {
        $itemIds = array_values(array_filter($itemIds, static fn (int $id): bool => $id > 0));
        if ([] === $itemIds) {
            return 0;
        }

        $deleted = 0;
        foreach ($this->itemRepository->findBy(['id' => $itemIds]) as $item) {
            if ($item->getGallery()->getId() === $gallery->getId()) {
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

    protected function createGalleryItem(): GalleryItemInterface
    {
        return new GalleryItem();
    }
}
