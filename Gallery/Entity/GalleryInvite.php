<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryInviteRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_invites')]
#[ORM\UniqueConstraint(name: 'uniq_invite_token', columns: ['token'])]
#[ORM\UniqueConstraint(name: 'uniq_invite_per_email', columns: ['gallery_id', 'email'])]
#[ORM\Index(name: 'idx_invite_visitor_token', columns: ['visitor_token'])]
class GalleryInvite extends AbstractGalleryInvite
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_invite_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
