<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Setting;

use Aurora\Core\Module\Toggle\ModuleToggle;
use Aurora\Module\Configuration\Setting\Enum\ApplicationParameterEnumInterface;

/**
 * Photo module's own access toggles (one row each in core_settings, group
 * `modules`). Self-contained so the Photo module declares its toggles without
 * the central `ModuleParameterEnum` — that core enum no longer knows about
 * Photo (monorepo-split: each module owns its toggle definitions).
 *
 * Exposed to the toggle machinery via {@see PhotoModule}
 * (getToggles) and to the settings sync via
 * {@see PhotoModuleParameterProvider}. Stored keys are unchanged from the
 * legacy central enum (no migration / no settings wipe).
 */
enum PhotoModuleParameterEnum: string implements ApplicationParameterEnumInterface
{
    private const string GROUP = 'modules';

    case Backend = 'modules_photo_backend';
    case Frontend = 'modules_photo_frontend';
    case Galleries = 'modules_photo_galleries';

    public function getKey(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Backend => 'backend.modules.photo_backend',
            self::Frontend => 'backend.modules.photo_frontend',
            self::Galleries => 'backend.nav.galleries',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Backend => 'backend.modules.photo_backend_description',
            self::Frontend => 'backend.modules.photo_frontend_description',
            self::Galleries => 'backend.nav.galleries_description',
        };
    }

    public function getDefaultValue(): string
    {
        return '1';
    }

    public function getType(): string
    {
        return 'bool';
    }

    public function getGroup(): string
    {
        return self::GROUP;
    }

    public function getPlaceholder(): ?string
    {
        return null;
    }

    /** Module identifier for the top-level toggle, null for sub-toggles. */
    private function getModuleId(): ?string
    {
        return self::Backend === $this ? 'photo' : null;
    }

    /** Cascade dependency (parent that must be ON), null for the top-level. */
    private function getCascadeRequires(): ?string
    {
        return match ($this) {
            self::Frontend, self::Galleries => self::Backend->value,
            default => null,
        };
    }

    /**
     * Structural parent for dashboard grouping. Null for the top-level Backend
     * toggle. Galleries and the public-galleries Frontend both nest under it.
     */
    private function getDisplayParent(): ?string
    {
        return self::Backend === $this ? null : self::Backend->value;
    }

    public function toToggle(): ModuleToggle
    {
        return new ModuleToggle(
            key: $this->value,
            labelKey: $this->getLabel(),
            descriptionKey: $this->getDescription(),
            parentKey: $this->getCascadeRequires(),
            moduleId: $this->getModuleId(),
            displayParentKey: $this->getDisplayParent(),
        );
    }
}
