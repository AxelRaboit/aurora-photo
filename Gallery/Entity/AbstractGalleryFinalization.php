<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGalleryFinalization implements GalleryFinalizationInterface
{
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    protected ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: GalleryInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected GalleryInterface $gallery;

    #[ORM\Column(length: 64)]
    protected string $visitorToken;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $visitorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $visitorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $finalizedAt;

    public function __construct()
    {
        $this->finalizedAt = new DateTimeImmutable();
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

    public function getGallery(): GalleryInterface
    {
        return $this->gallery;
    }

    public function setGallery(GalleryInterface $gallery): static
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
