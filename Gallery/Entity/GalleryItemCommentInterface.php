<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Entity;

use DateTimeImmutable;

interface GalleryItemCommentInterface
{
    public function getId(): ?int;

    public function getReference(): ?string;

    public function setReference(?string $reference): static;

    public function getGalleryItem(): GalleryItemInterface;

    public function setGalleryItem(GalleryItemInterface $galleryItem): static;

    public function getVisitorToken(): string;

    public function setVisitorToken(string $visitorToken): static;

    public function getVisitorName(): ?string;

    public function setVisitorName(?string $visitorName): static;

    public function getVisitorEmail(): ?string;

    public function setVisitorEmail(?string $visitorEmail): static;

    public function getContent(): string;

    public function setContent(string $content): static;

    public function getCreatedAt(): DateTimeImmutable;
}
