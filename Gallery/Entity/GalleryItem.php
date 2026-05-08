<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryItemRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_items')]
#[ORM\UniqueConstraint(name: 'uniq_gallery_media', columns: ['gallery_id', 'media_id'])]
#[ORM\UniqueConstraint(name: 'uniq_gallery_number', columns: ['gallery_id', 'number'])]
#[ORM\Index(name: 'idx_gallery_position', columns: ['gallery_id', 'position'])]
class GalleryItem extends AbstractGalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_item_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
