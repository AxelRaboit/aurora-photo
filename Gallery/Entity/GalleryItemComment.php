<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Free-text feedback left by a visitor on a single photo. Surfaces in the
 * admin gallery editor so the photographer sees per-photo notes alongside picks.
 */
#[ORM\Entity(repositoryClass: GalleryItemCommentRepository::class)]
#[ORM\Table(name: 'photo_gallery_item_comments')]
#[ORM\Index(name: 'idx_comment_item', columns: ['gallery_item_id'])]
#[ORM\Index(name: 'idx_comment_token', columns: ['visitor_token'])]
class GalleryItemComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GalleryItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GalleryItem $galleryItem;

    #[ORM\Column(length: 64)]
    private string $visitorToken;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $visitorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $visitorEmail = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGalleryItem(): GalleryItem
    {
        return $this->galleryItem;
    }

    public function setGalleryItem(GalleryItem $galleryItem): static
    {
        $this->galleryItem = $galleryItem;

        return $this;
    }

    public function getVisitorToken(): string
    {
        return $this->visitorToken;
    }

    public function setVisitorToken(string $visitorToken): static
    {
        $this->visitorToken = $visitorToken;

        return $this;
    }

    public function getVisitorName(): ?string
    {
        return $this->visitorName;
    }

    public function setVisitorName(?string $visitorName): static
    {
        $this->visitorName = $visitorName;

        return $this;
    }

    public function getVisitorEmail(): ?string
    {
        return $this->visitorEmail;
    }

    public function setVisitorEmail(?string $visitorEmail): static
    {
        $this->visitorEmail = $visitorEmail;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
