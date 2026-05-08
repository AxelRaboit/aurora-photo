<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryItemInterface>
 */
class GalleryItemRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryItem::class, GalleryItemInterface::class);
    }

    public function findInGallery(int $itemId, int $galleryId): ?GalleryItem
    {
        $item = $this->find($itemId);

        return $item instanceof GalleryItem && $item->getGallery()->getId() === $galleryId ? $item : null;
    }

    public function nextPositionForGallery(int $galleryId): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.position)')
            ->andWhere('i.gallery = :id')
            ->setParameter('id', $galleryId)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }

    public function nextNumberForGallery(int $galleryId): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.number)')
            ->andWhere('i.gallery = :id')
            ->setParameter('id', $galleryId)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 1 : (int) $max + 1;
    }
}
