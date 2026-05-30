<?php

declare(strict_types=1);

namespace Aurora\Module\Photo;

use Aurora\Core\Bundle\AbstractAuroraModuleBundle;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalizationInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryPick;
use Aurora\Module\Photo\Gallery\Entity\GalleryPickInterface;

/** Self-contained bundle for the Photo module. @see AbstractAuroraModuleBundle */
final class AuroraPhotoBundle extends AbstractAuroraModuleBundle
{
    protected function moduleName(): string
    {
        return 'Photo';
    }

    protected function resolveTargetEntities(): array
    {
        return [
            GalleryInterface::class => Gallery::class,
            GalleryFinalizationInterface::class => GalleryFinalization::class,
            GalleryInviteInterface::class => GalleryInvite::class,
            GalleryItemInterface::class => GalleryItem::class,
            GalleryItemCommentInterface::class => GalleryItemComment::class,
            GalleryPickInterface::class => GalleryPick::class,
        ];
    }
}
