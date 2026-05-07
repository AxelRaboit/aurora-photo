<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Core\Media\Entity\Media;
use Aurora\Core\Trait\TimestampableTrait;
use Aurora\Core\User\Entity\User;
use Aurora\Module\Crm\Contact\Entity\Contact;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ORM\Table(name: 'core_photo_galleries')]
#[ORM\HasLifecycleCallbacks]
class Gallery
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_gallery_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $slug;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Bcrypt hash of the gallery password. Null = no password gate.
     */
    #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $coverMedia = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $allowOriginals = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $allowZipDownload = true;

    /**
     * When true, visitors must enter name + email before they can pick. When
     * false, picks are tracked anonymously via the visitor cookie token only.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $picksRequireIdentity = false;

    /**
     * Hard cap on the number of "favorite" picks a single visitor can make.
     * Null = unlimited. Other pick kinds (print/discard) are not capped.
     */
    #[ORM\Column(name: 'max_picks', nullable: true)]
    private ?int $maxPicks = null;

    /**
     * Allow visitors to leave a free-text comment under each photo. The
     * comment is visible to the admin in the gallery editor.
     */
    #[ORM\Column(name: 'allow_visitor_comments', options: ['default' => false])]
    private bool $allowVisitorComments = false;

    /**
     * When true, the watermark text is overlaid on every "web" variant served
     * by the public download endpoint. Originals are never watermarked.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $watermarkEnabled = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $watermarkText = null;

    /**
     * Set when the client clicks "I'm done with my selection" — triggers the
     * notification email to the admin and freezes further pick changes.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finalizedAt = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $finalizedByName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $finalizedByEmail = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    /**
     * Optional CRM contact this gallery is for. Soft link — the gallery
     * survives if the contact is deleted (FK nullable + ON DELETE SET NULL),
     * and works fine even when the CRM module is disabled.
     */
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: 'client_contact_id', nullable: true, onDelete: 'SET NULL')]
    private ?Contact $clientContact = null;

    /** @var Collection<int, GalleryItem> */
    #[ORM\OneToMany(targetEntity: GalleryItem::class, mappedBy: 'gallery', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => Order::Ascending->value])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCoverMedia(): ?Media
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?Media $coverMedia): static
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

    /**
     * True only if the gallery actually applies a watermark (toggle on AND
     * non-empty text). Used by download services to short-circuit work.
     */
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

    public function getClientContact(): ?Contact
    {
        return $this->clientContact;
    }

    public function setClientContact(?Contact $clientContact): static
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

    /**
     * @return Collection<int, GalleryItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }
}
