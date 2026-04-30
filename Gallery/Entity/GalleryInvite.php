<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Magic-link invitation: each invitee gets a unique URL that auto-unlocks the
 * gallery and pre-captures their identity. Replaces the shared password model
 * for groups (couples, families) where the photographer wants to track who
 * actually viewed the gallery.
 */
#[ORM\Entity(repositoryClass: GalleryInviteRepository::class)]
#[ORM\Table(name: 'photo_gallery_invites')]
#[ORM\UniqueConstraint(name: 'uniq_invite_token', columns: ['token'])]
#[ORM\UniqueConstraint(name: 'uniq_invite_per_email', columns: ['gallery_id', 'email'])]
#[ORM\Index(name: 'idx_invite_visitor_token', columns: ['visitor_token'])]
class GalleryInvite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Gallery $gallery;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64)]
    private string $token;

    /**
     * Deterministic visitor token derived from the invite token at creation
     * time, indexed for fast lookup so picks/comments can be attributed back
     * to the named invitee without walking the picks table.
     */
    #[ORM\Column(name: 'visitor_token', length: 64)]
    private string $visitorToken;

    #[ORM\Column(name: 'invited_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $invitedAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->invitedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

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

    public function getInvitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getLastSeenAt(): ?DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function markSeen(): static
    {
        $this->lastSeenAt = new DateTimeImmutable();

        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(): static
    {
        $this->sentAt = new DateTimeImmutable();

        return $this;
    }
}
