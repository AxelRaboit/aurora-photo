<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;

interface GalleryInviteManagerInterface
{
    public function create(GalleryInterface $gallery, string $name, string $email): GalleryInviteInterface;

    public function delete(GalleryInviteInterface $invite): void;

    public function send(GalleryInviteInterface $invite): void;

    public function markSeen(GalleryInviteInterface $invite): void;
}
