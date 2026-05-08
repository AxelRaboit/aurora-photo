<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use DateTimeImmutable;

interface GalleryFinalizationInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getGallery(): GalleryInterface;

    public function setGallery(GalleryInterface $gallery): static;

    public function getVisitorToken(): string;

    public function setVisitorToken(string $visitorToken): static;

    public function getVisitorName(): ?string;

    public function setVisitorName(?string $visitorName): static;

    public function getVisitorEmail(): ?string;

    public function setVisitorEmail(?string $visitorEmail): static;

    public function getFinalizedAt(): DateTimeImmutable;
}
