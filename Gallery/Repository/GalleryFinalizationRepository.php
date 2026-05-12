<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalizationInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryFinalizationInterface>
 */
class GalleryFinalizationRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryFinalization::class, GalleryFinalizationInterface::class);
    }

    public function findOneByVisitor(int $galleryId, string $visitorToken): ?GalleryFinalizationInterface
    {
        return $this->findOneBy(['gallery' => $galleryId, 'visitorToken' => $visitorToken]);
    }

    public function findInGallery(int $finalizationId, int $galleryId): ?GalleryFinalizationInterface
    {
        $finalization = $this->find($finalizationId);

        return $finalization instanceof GalleryFinalizationInterface && $finalization->getGallery()->getId() === $galleryId ? $finalization : null;
    }

    /**
     * @return list<GalleryFinalizationInterface>
     */
    public function findAllForGallery(int $galleryId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.gallery = :gid')->setParameter('gid', $galleryId)
            ->orderBy('f.finalizedAt', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    public function countForGallery(int $galleryId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.gallery = :gid')->setParameter('gid', $galleryId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns a map of galleryId → finalization count for the given gallery IDs.
     * Fires a single GROUP BY query instead of one COUNT per gallery.
     *
     * @param list<int> $galleryIds
     *
     * @return array<int, int>
     */
    public function countByGalleries(array $galleryIds): array
    {
        if ([] === $galleryIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.gallery) AS gid, COUNT(f.id) AS cnt')
            ->where('f.gallery IN (:ids)')
            ->setParameter('ids', $galleryIds)
            ->groupBy('f.gallery')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'cnt', 'gid');
    }
}
