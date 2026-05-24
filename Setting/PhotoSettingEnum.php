<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Setting;

use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnumInterface;

enum PhotoSettingEnum: string implements ApplicationParameterEnumInterface
{
    case GalleryPrefix = 'photo_gallery_prefix';
    case GalleryItemPrefix = 'photo_gallery_item_prefix';
    case GalleryInvitePrefix = 'photo_gallery_invite_prefix';
    case GalleryFinalizationPrefix = 'photo_gallery_finalization_prefix';
    case GalleryItemCommentPrefix = 'photo_gallery_item_comment_prefix';
    case GalleryPickPrefix = 'photo_gallery_pick_prefix';

    public function getKey(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::GalleryPrefix => 'backend.parameters.photo_gallery_prefix.label',
            self::GalleryItemPrefix => 'backend.parameters.photo_gallery_item_prefix.label',
            self::GalleryInvitePrefix => 'backend.parameters.photo_gallery_invite_prefix.label',
            self::GalleryFinalizationPrefix => 'backend.parameters.photo_gallery_finalization_prefix.label',
            self::GalleryItemCommentPrefix => 'backend.parameters.photo_gallery_item_comment_prefix.label',
            self::GalleryPickPrefix => 'backend.parameters.photo_gallery_pick_prefix.label',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::GalleryPrefix => 'backend.parameters.photo_gallery_prefix.description',
            self::GalleryItemPrefix => 'backend.parameters.photo_gallery_item_prefix.description',
            self::GalleryInvitePrefix => 'backend.parameters.photo_gallery_invite_prefix.description',
            self::GalleryFinalizationPrefix => 'backend.parameters.photo_gallery_finalization_prefix.description',
            self::GalleryItemCommentPrefix => 'backend.parameters.photo_gallery_item_comment_prefix.description',
            self::GalleryPickPrefix => 'backend.parameters.photo_gallery_pick_prefix.description',
        };
    }

    public function getDefaultValue(): string
    {
        return match ($this) {
            self::GalleryPrefix => SequencePrefixEnum::Gallery->value,
            self::GalleryItemPrefix => SequencePrefixEnum::GalleryItem->value,
            self::GalleryInvitePrefix => SequencePrefixEnum::GalleryInvite->value,
            self::GalleryFinalizationPrefix => SequencePrefixEnum::GalleryFinalization->value,
            self::GalleryItemCommentPrefix => SequencePrefixEnum::GalleryItemComment->value,
            self::GalleryPickPrefix => SequencePrefixEnum::GalleryPick->value,
        };
    }

    public function getType(): string
    {
        return 'string';
    }

    public function getGroup(): string
    {
        return 'sequences';
    }

    /**
     * No placeholder by default — override on a per-case basis when an
     * example value is genuinely clearer than the description alone.
     */
    public function getPlaceholder(): ?string
    {
        return null;
    }
}
