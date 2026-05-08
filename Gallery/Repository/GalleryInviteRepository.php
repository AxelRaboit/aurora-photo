<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Repository;

use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Core\Repository\ResolveTargetEntityRepository;
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

    public function findOneByToken(string $token): ?GalleryInvite
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findInGallery(int $inviteId, int $galleryId): ?GalleryInvite
    {
        $invite = $this->find($inviteId);

        return $invite instanceof GalleryInvite && $invite->getGallery()->getId() === $galleryId ? $invite : null;
    }

    public function findOneByGalleryAndEmail(int $galleryId, string $email): ?GalleryInvite
    {
        return $this->findOneBy(['gallery' => $galleryId, 'email' => $email]);
    }

    /**
     * @return list<GalleryInvite>
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
