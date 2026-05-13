<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Core\Repository\Trait\PaginationTrait;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryInterface>
 */
class GalleryRepository extends ResolveTargetEntityRepository
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class, GalleryInterface::class);
    }

    /**
     * @return array{items: list<Gallery>, total: int, page: int, totalPages: int}
     */
    public function findPaginated(int $page, int $limit = 20, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.coverMedia', 'cm')->addSelect('cm')
            ->leftJoin('g.clientContact', 'cc')->addSelect('cc')
            ->orderBy('g.createdAt', Order::Descending->value);

        $countQb = $this->createQueryBuilder('g')->select('COUNT(g.id)');

        if (null !== $search && '' !== $search) {
            $pattern = '%'.mb_strtolower($search).'%';
            $qb->andWhere('LOWER(g.title) LIKE :search OR LOWER(g.slug) LIKE :search')->setParameter('search', $pattern);
            $countQb->andWhere('LOWER(g.title) LIKE :search OR LOWER(g.slug) LIKE :search')->setParameter('search', $pattern);
        }

        return $this->paginate($qb, $countQb, $page, $limit);
    }

    public function findOneBySlug(string $slug): ?GalleryInterface
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Returns a map of galleryId → item count for the given gallery IDs.
     * Fires a single GROUP BY query instead of lazy-loading items per gallery.
     *
     * @param list<int> $galleryIds
     *
     * @return array<int, int>
     */
    public function countItemsByGalleries(array $galleryIds): array
    {
        if ([] === $galleryIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('g')
            ->select('g.id AS gid, COUNT(i.id) AS cnt')
            ->leftJoin('g.items', 'i')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $galleryIds)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'cnt', 'gid');
    }

    /**
     * Eagerly loads items + their underlying media in a single query so
     * the admin edit page (which iterates ~all items) doesn't fire one
     * extra SELECT per item to fetch the Media entity.
     */
    public function findOneWithItemsAndMedia(int $id): ?Gallery
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.items', 'i')->addSelect('i')
            ->leftJoin('i.media', 'm')->addSelect('m')
            ->andWhere('g.id = :id')->setParameter('id', $id)
            ->orderBy('i.position', Order::Ascending->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isSlugTaken(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('g')->select('COUNT(g.id)')->andWhere('g.slug = :slug')->setParameter('slug', $slug);
        if (null !== $excludeId) {
            $qb->andWhere('g.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.expiresAt IS NULL OR g.expiresAt > :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countFinalized(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.finalizedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
