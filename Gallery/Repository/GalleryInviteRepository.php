<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Core\Repository\ResolveTargetEntityRepository;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ResolveTargetEntityRepository<GalleryInviteInterface>
 */
class GalleryInviteRepository extends ResolveTargetEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryInvite::class, GalleryInviteInterface::class);
    }

    public function findOneByToken(string $token): ?GalleryInviteInterface
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findInGallery(int $inviteId, int $galleryId): ?GalleryInviteInterface
    {
        $invite = $this->find($inviteId);

        return $invite instanceof GalleryInviteInterface && $invite->getGallery()->getId() === $galleryId ? $invite : null;
    }

    public function findOneByGalleryAndEmail(int $galleryId, string $email): ?GalleryInviteInterface
    {
        return $this->findOneBy(['gallery' => $galleryId, 'email' => $email]);
    }

    /**
     * @return list<GalleryInviteInterface>
     */
    public function findAllForGallery(int $galleryId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.gallery = :gid')->setParameter('gid', $galleryId)
            ->orderBy('i.invitedAt', Order::Ascending->value)
            ->getQuery()
            ->getResult();
    }
}
