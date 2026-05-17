<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use Aurora\Core\Media\Library\Entity\MediaInterface;
use DateTimeImmutable;

interface GalleryItemInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getGallery(): GalleryInterface;

    public function setGallery(GalleryInterface $gallery): static;

    public function getMedia(): MediaInterface;

    public function setMedia(MediaInterface $media): static;

    public function getPosition(): int;

    public function setPosition(int $position): static;

    public function getNumber(): int;

    public function setNumber(int $number): static;

    public function getTakenAt(): ?DateTimeImmutable;

    public function setTakenAt(?DateTimeImmutable $takenAt): static;

    public function getCaption(): ?string;

    public function setCaption(?string $caption): static;
}
