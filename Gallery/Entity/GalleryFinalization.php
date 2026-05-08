<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryFinalizationRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_finalizations')]
#[ORM\UniqueConstraint(name: 'uniq_finalization_per_visitor', columns: ['gallery_id', 'visitor_token'])]
#[ORM\Index(name: 'idx_finalization_token', columns: ['visitor_token'])]
class GalleryFinalization extends AbstractGalleryFinalization
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_finalization_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
