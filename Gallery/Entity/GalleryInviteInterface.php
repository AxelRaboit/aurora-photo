<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use DateTimeImmutable;

interface GalleryInviteInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getGallery(): GalleryInterface;

    public function setGallery(GalleryInterface $gallery): static;

    public function getName(): string;

    public function setName(string $name): static;

    public function getEmail(): string;

    public function setEmail(string $email): static;

    public function getToken(): string;

    public function setToken(string $token): static;

    public function getVisitorToken(): string;

    public function setVisitorToken(string $visitorToken): static;

    public function getInvitedAt(): DateTimeImmutable;

    public function getLastSeenAt(): ?DateTimeImmutable;

    public function markSeen(): static;

    public function getSentAt(): ?DateTimeImmutable;

    public function markSent(): static;
}
