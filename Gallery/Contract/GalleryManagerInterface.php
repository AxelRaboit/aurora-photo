<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Contract;

use Aurora\Core\User\Entity\User;
use Aurora\Module\Photo\Gallery\Dto\GalleryInput;
use Aurora\Module\Photo\Gallery\Entity\Gallery;

interface GalleryManagerInterface
{
    public function create(GalleryInput $input, User $createdBy): Gallery;

    public function update(Gallery $gallery, GalleryInput $input): void;

    public function reopen(Gallery $gallery): void;

    public function delete(Gallery $gallery): void;
}
