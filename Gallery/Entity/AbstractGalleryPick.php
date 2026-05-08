<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGalleryPick implements GalleryPickInterface
{
    #[ORM\Column(length: 32, unique: true, nullable: true)]
    protected ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: GalleryItemInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected GalleryItemInterface $galleryItem;

    #[ORM\Column(length: 64)]
    protected string $visitorToken;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $visitorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $visitorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $pickedAt;

    #[ORM\Column(length: 32, enumType: PickKindEnum::class, options: ['default' => 'favorite'])]
    protected PickKindEnum $kind = PickKindEnum::Favorite;

    public function __construct()
    {
        $this->pickedAt = new DateTimeImmutable();
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

    public function getGalleryItem(): GalleryItemInterface
    {
        return $this->galleryItem;
    }

    public function setGalleryItem(GalleryItemInterface $galleryItem): static
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

    public function getPickedAt(): DateTimeImmutable
    {
        return $this->pickedAt;
    }

    public function getKind(): PickKindEnum
    {
        return $this->kind;
    }

    public function setKind(PickKindEnum $kind): static
    {
        $this->kind = $kind;

        return $this;
    }
}
