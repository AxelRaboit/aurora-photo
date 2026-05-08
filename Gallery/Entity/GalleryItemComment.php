<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryItemCommentRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_item_comments')]
#[ORM\Index(name: 'idx_comment_item', columns: ['gallery_item_id'])]
#[ORM\Index(name: 'idx_comment_token', columns: ['visitor_token'])]
class GalleryItemComment extends AbstractGalleryItemComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_item_comment_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
