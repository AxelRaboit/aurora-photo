<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Core\Media\Library\Entity\MediaInterface;
use Aurora\Core\Timestampable\TimestampableTrait;
use Aurora\Core\Platform\User\Entity\User;
use Aurora\Module\Crm\Contact\Entity\ContactInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractGallery implements GalleryInterface
{
    use TimestampableTrait;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    protected ?string $reference = null;

    #[ORM\Column(length: 80, unique: true)]
    protected string $slug;

    #[ORM\Column(length: 200)]
    protected string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
    protected ?string $passwordHash = null;

    #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?MediaInterface $coverMedia = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(options: ['default' => true])]
    protected bool $allowOriginals = true;

    #[ORM\Column(options: ['default' => true])]
    protected bool $allowZipDownload = true;

    #[ORM\Column(options: ['default' => false])]
    protected bool $picksRequireIdentity = false;

    #[ORM\Column(name: 'max_picks', nullable: true)]
    protected ?int $maxPicks = null;

    #[ORM\Column(name: 'allow_visitor_comments', options: ['default' => false])]
    protected bool $allowVisitorComments = false;

    #[ORM\Column(options: ['default' => false])]
    protected bool $watermarkEnabled = false;

    #[ORM\Column(length: 100, nullable: true)]
    protected ?string $watermarkText = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $finalizedAt = null;

    #[ORM\Column(length: 200, nullable: true)]
    protected ?string $finalizedByName = null;

    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $finalizedByEmail = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    protected User $createdBy;

    #[ORM\ManyToOne(targetEntity: ContactInterface::class)]
    #[ORM\JoinColumn(name: 'client_contact_id', nullable: true, onDelete: 'SET NULL')]
    protected ?ContactInterface $clientContact = null;

    /** @var Collection<int, GalleryItemInterface> */
    #[ORM\OneToMany(targetEntity: GalleryItemInterface::class, mappedBy: 'gallery', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => Order::Ascending->value])]
    protected Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function hasPassword(): bool
    {
        return null !== $this->passwordHash && '' !== $this->passwordHash;
    }

    public function getCoverMedia(): ?MediaInterface
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?MediaInterface $coverMedia): static
    {
        $this->coverMedia = $coverMedia;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt < new DateTimeImmutable();
    }

    public function isAllowOriginals(): bool
    {
        return $this->allowOriginals;
    }

    public function setAllowOriginals(bool $allowOriginals): static
    {
        $this->allowOriginals = $allowOriginals;

        return $this;
    }

    public function isAllowZipDownload(): bool
    {
        return $this->allowZipDownload;
    }

    public function setAllowZipDownload(bool $allowZipDownload): static
    {
        $this->allowZipDownload = $allowZipDownload;

        return $this;
    }

    public function isPicksRequireIdentity(): bool
    {
        return $this->picksRequireIdentity;
    }

    public function setPicksRequireIdentity(bool $picksRequireIdentity): static
    {
        $this->picksRequireIdentity = $picksRequireIdentity;

        return $this;
    }

    public function getMaxPicks(): ?int
    {
        return $this->maxPicks;
    }

    public function setMaxPicks(?int $maxPicks): static
    {
        $this->maxPicks = null !== $maxPicks && $maxPicks > 0 ? $maxPicks : null;

        return $this;
    }

    public function isAllowVisitorComments(): bool
    {
        return $this->allowVisitorComments;
    }

    public function setAllowVisitorComments(bool $allowVisitorComments): static
    {
        $this->allowVisitorComments = $allowVisitorComments;

        return $this;
    }

    public function isWatermarkEnabled(): bool
    {
        return $this->watermarkEnabled;
    }

    public function setWatermarkEnabled(bool $watermarkEnabled): static
    {
        $this->watermarkEnabled = $watermarkEnabled;

        return $this;
    }

    public function getWatermarkText(): ?string
    {
        return $this->watermarkText;
    }

    public function setWatermarkText(?string $watermarkText): static
    {
        $this->watermarkText = $watermarkText;

        return $this;
    }

    public function hasActiveWatermark(): bool
    {
        return $this->watermarkEnabled && null !== $this->watermarkText && '' !== mb_trim($this->watermarkText);
    }

    public function getFinalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function setFinalizedAt(?DateTimeImmutable $finalizedAt): static
    {
        $this->finalizedAt = $finalizedAt;

        return $this;
    }

    public function isFinalized(): bool
    {
        return $this->finalizedAt instanceof DateTimeImmutable;
    }

    public function getFinalizedByName(): ?string
    {
        return $this->finalizedByName;
    }

    public function setFinalizedByName(?string $finalizedByName): static
    {
        $this->finalizedByName = $finalizedByName;

        return $this;
    }

    public function getFinalizedByEmail(): ?string
    {
        return $this->finalizedByEmail;
    }

    public function setFinalizedByEmail(?string $finalizedByEmail): static
    {
        $this->finalizedByEmail = $finalizedByEmail;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getClientContact(): ?ContactInterface
    {
        return $this->clientContact;
    }

    public function setClientContact(?ContactInterface $clientContact): static
    {
        $this->clientContact = $clientContact;

        return $this;
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

    public function getItems(): Collection
    {
        return $this->items;
    }
}
