<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Module\Photo\Gallery\Entity\GalleryPick;
use Aurora\Module\Photo\Gallery\Entity\GalleryPickInterface;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryPickInterface>
 */
class GalleryPickRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryPick::class, GalleryPickInterface::class);
    }

    /**
     * @return list<GalleryPick>
     */
    public function findByVisitorForGallery(string $visitorToken, int $galleryId): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.galleryItem', 'i')
            ->andWhere('p.visitorToken = :token')->setParameter('token', $visitorToken)
            ->andWhere('i.gallery = :gid')->setParameter('gid', $galleryId)
            ->getQuery()
            ->getResult();
    }

    public function countForVisitor(string $visitorToken, int $galleryId, PickKindEnum $kind): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.galleryItem', 'i')
            ->andWhere('p.visitorToken = :token')->setParameter('token', $visitorToken)
            ->andWhere('i.gallery = :gid')->setParameter('gid', $galleryId)
            ->andWhere('p.kind = :kind')->setParameter('kind', $kind->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<GalleryPick>
     */
    public function findAllForGallery(int $galleryId): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.galleryItem', 'i')->addSelect('i')
            ->andWhere('i.gallery = :gid')->setParameter('gid', $galleryId)
            ->orderBy('p.pickedAt', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }
}
