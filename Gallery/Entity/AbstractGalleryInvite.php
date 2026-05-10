<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractGalleryInvite implements GalleryInviteInterface
{
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    protected ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: GalleryInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected GalleryInterface $gallery;

    #[ORM\Column(length: 200)]
    protected string $name;

    #[ORM\Column(length: 180)]
    protected string $email;

    #[ORM\Column(length: 64)]
    protected string $token;

    #[ORM\Column(name: 'visitor_token', length: 64)]
    protected string $visitorToken;

    #[ORM\Column(name: 'invited_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $invitedAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->invitedAt = new DateTimeImmutable();
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
