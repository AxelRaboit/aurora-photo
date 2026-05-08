<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Core\Media\Entity\MediaInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryItemRepository::class)]
#[ORM\Table(name: 'core_photo_gallery_items')]
#[ORM\UniqueConstraint(name: 'uniq_gallery_media', columns: ['gallery_id', 'media_id'])]
#[ORM\UniqueConstraint(name: 'uniq_gallery_number', columns: ['gallery_id', 'number'])]
#[ORM\Index(name: 'idx_gallery_position', columns: ['gallery_id', 'position'])]
class GalleryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_item_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Gallery $gallery;

    #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaInterface $media;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /**
     * Stable per-gallery sequence number assigned at creation. Unlike position,
     * this number never changes when items are reordered or deleted — used so
     * visitors and the photographer can refer to "photo #42" unambiguously.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $number = 0;

    /**
     * EXIF DateTimeOriginal extracted at item creation. Used by the front to
     * group consecutive items shot &lt;2s apart into "bursts" so visitors can
     * sift through near-duplicate frames quickly.
     */
    #[ORM\Column(name: 'taken_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $takenAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getGallery(): Gallery
    {
        return $this->gallery;
    }

    public function setGallery(Gallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function getMedia(): MediaInterface
    {
        return $this->media;
    }

    public function setMedia(MediaInterface $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getTakenAt(): ?DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function setTakenAt(?DateTimeImmutable $takenAt): static
    {
        $this->takenAt = $takenAt;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }
}
