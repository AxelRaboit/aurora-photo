<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Serializer;

use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;

interface GallerySerializerInterface
{
    /** @return array<string, mixed> */
    public function serialize(GalleryInterface $gallery): array;

    /** @return list<array<string, mixed>> */
    public function serializeInvites(GalleryInterface $gallery): array;

    /** @return list<array<string, mixed>> */
    public function serializeItems(GalleryInterface $gallery): array;

    /** @return array<string, mixed> */
    public function serializePickStats(GalleryInterface $gallery): array;

    /** @return list<array<string, mixed>> */
    public function serializeComments(GalleryInterface $gallery): array;

    /** @return array<string, mixed> */
    public function serializeComment(GalleryItemCommentInterface $comment): array;

    /** @return list<array<string, mixed>> */
    public function serializeFinalizations(GalleryInterface $gallery): array;

    /**
     * @param array{items: list<GalleryInterface>, total: int, page: int, totalPages: int} $paginated
     *
     * @return array<string, mixed>
     */
    public function serializeListPayload(array $paginated): array;
}
