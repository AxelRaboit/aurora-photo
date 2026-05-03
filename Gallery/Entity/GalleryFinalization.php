<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per visitor (token) who validated their selection on a gallery.
 * Multi-validation: several invitees can each finalize their own picks
 * independently. The optional Gallery::finalized* fields are kept as a
 * separate global lock the photographer may set to close the gallery.
 */
#[ORM\Entity(repositoryClass: GalleryFinalizationRepository::class)]
#[ORM\Table(name: 'photo_gallery_finalizations')]
#[ORM\UniqueConstraint(name: 'uniq_finalization_per_visitor', columns: ['gallery_id', 'visitor_token'])]
#[ORM\Index(name: 'idx_finalization_token', columns: ['visitor_token'])]
class GalleryFinalization
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_finalization_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Gallery $gallery;

    #[ORM\Column(length: 64)]
    private string $visitorToken;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $visitorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $visitorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $finalizedAt;

    public function __construct()
    {
        $this->finalizedAt = new DateTimeImmutable();
    }

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

    public function getFinalizedAt(): DateTimeImmutable
    {
        return $this->finalizedAt;
    }
}
