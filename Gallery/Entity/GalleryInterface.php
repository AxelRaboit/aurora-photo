<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Module\Media\Library\Entity\MediaInterface;
use Aurora\Core\Timestampable\TimestampableInterface;
use Aurora\Core\Platform\User\Entity\User;
use Aurora\Module\Crm\Contact\Entity\ContactInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;

interface GalleryInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getSlug(): string;

    public function setSlug(string $slug): static;

    public function getTitle(): string;

    public function setTitle(string $title): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getPasswordHash(): ?string;

    public function setPasswordHash(?string $passwordHash): static;

    public function hasPassword(): bool;

    public function getCoverMedia(): ?MediaInterface;

    public function setCoverMedia(?MediaInterface $coverMedia): static;

    public function getExpiresAt(): ?DateTimeImmutable;

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static;

    public function isExpired(): bool;

    public function isAllowOriginals(): bool;

    public function setAllowOriginals(bool $allowOriginals): static;

    public function isAllowZipDownload(): bool;

    public function setAllowZipDownload(bool $allowZipDownload): static;

    public function isPicksRequireIdentity(): bool;

    public function setPicksRequireIdentity(bool $picksRequireIdentity): static;

    public function getMaxPicks(): ?int;

    public function setMaxPicks(?int $maxPicks): static;

    public function isAllowVisitorComments(): bool;

    public function setAllowVisitorComments(bool $allowVisitorComments): static;

    public function isWatermarkEnabled(): bool;

    public function setWatermarkEnabled(bool $watermarkEnabled): static;

    public function getWatermarkText(): ?string;

    public function setWatermarkText(?string $watermarkText): static;

    public function hasActiveWatermark(): bool;

    public function getFinalizedAt(): ?DateTimeImmutable;

    public function setFinalizedAt(?DateTimeImmutable $finalizedAt): static;

    public function isFinalized(): bool;

    public function getFinalizedByName(): ?string;

    public function setFinalizedByName(?string $finalizedByName): static;

    public function getFinalizedByEmail(): ?string;

    public function setFinalizedByEmail(?string $finalizedByEmail): static;

    public function getCreatedBy(): User;

    public function setCreatedBy(User $createdBy): static;

    public function getClientContact(): ?ContactInterface;

    public function setClientContact(?ContactInterface $clientContact): static;

    /** @return Collection<int, GalleryItemInterface> */
    public function getItems(): Collection;
}
