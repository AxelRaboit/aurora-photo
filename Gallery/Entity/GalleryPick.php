<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A "favorite" cast by a visitor on a gallery item. The visitor is identified
 * by a HMAC cookie token issued at password unlock; visitorName / visitorEmail
 * are populated only when the gallery requires identity for picks.
 */
#[ORM\Entity(repositoryClass: GalleryPickRepository::class)]
#[ORM\Table(name: 'photo_gallery_picks')]
#[ORM\UniqueConstraint(name: 'uniq_pick_per_visitor', columns: ['gallery_item_id', 'visitor_token', 'kind'])]
#[ORM\Index(name: 'idx_pick_token', columns: ['visitor_token'])]
class GalleryPick
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_pick_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: GalleryItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GalleryItem $galleryItem;

    #[ORM\Column(length: 64)]
    private string $visitorToken;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $visitorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $visitorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $pickedAt;

    #[ORM\Column(length: 32, enumType: PickKindEnum::class, options: ['default' => 'favorite'])]
    private PickKindEnum $kind = PickKindEnum::Favorite;

    public function __construct()
    {
        $this->pickedAt = new DateTimeImmutable();
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
