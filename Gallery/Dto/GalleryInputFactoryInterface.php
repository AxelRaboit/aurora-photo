<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Dto;

interface GalleryInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): GalleryInputInterface;
}
