<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryPickRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_picks')]
#[ORM\UniqueConstraint(name: 'uniq_pick_per_visitor', columns: ['gallery_item_id', 'visitor_token', 'kind'])]
#[ORM\Index(name: 'idx_pick_token', columns: ['visitor_token'])]
class GalleryPick extends AbstractGalleryPick
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_gallery_pick_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
