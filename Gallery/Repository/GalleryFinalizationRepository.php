<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryFinalization>
 */
class GalleryFinalizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryFinalization::class);
    }

    public function findOneByVisitor(int $galleryId, string $visitorToken): ?GalleryFinalization
    {
        return $this->findOneBy(['gallery' => $galleryId, 'visitorToken' => $visitorToken]);
    }

    public function findInGallery(int $finalizationId, int $galleryId): ?GalleryFinalization
    {
        $finalization = $this->find($finalizationId);

        return $finalization instanceof GalleryFinalization && $finalization->getGallery()->getId() === $galleryId ? $finalization : null;
    }

    /**
     * @return list<GalleryFinalization>
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
}
