<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Core\Platform\User\Entity\User;
use Aurora\Module\Photo\Gallery\Dto\GalleryInputInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;

interface GalleryManagerInterface
{
    public function create(GalleryInputInterface $input, User $createdBy): GalleryInterface;

    public function update(GalleryInterface $gallery, GalleryInputInterface $input): void;

    public function reopen(GalleryInterface $gallery): void;

    public function delete(GalleryInterface $gallery): void;
}
