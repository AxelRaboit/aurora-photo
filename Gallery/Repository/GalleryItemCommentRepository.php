<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryItemCommentInterface>
 */
class GalleryItemCommentRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryItemComment::class, GalleryItemCommentInterface::class);
    }

    public function findInGallery(int $commentId, int $galleryId): ?GalleryItemComment
    {
        $comment = $this->find($commentId);

        return $comment instanceof GalleryItemComment && $comment->getGalleryItem()->getGallery()->getId() === $galleryId ? $comment : null;
    }

    /**
     * @return list<GalleryItemComment>
     */
    public function findByItem(int $galleryItemId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.galleryItem = :id')->setParameter('id', $galleryItemId)
            ->orderBy('c.createdAt', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all comments for a gallery, joined for one-shot rendering.
     *
     * @return list<GalleryItemComment>
     */
    public function findAllForGallery(int $galleryId): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.galleryItem', 'i')->addSelect('i')
            ->andWhere('i.gallery = :gid')->setParameter('gid', $galleryId)
            ->orderBy('c.createdAt', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }
}
