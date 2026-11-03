<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Enums\RealmThemeSection;
use App\Realms\Enums\RealmThemeSettingType;

final readonly class RealmThemeSetting
{
    public function __construct(
        public string $name,
        public RealmThemeSection $section,
        public RealmThemeSettingType $type,
    ) {}

    /**
     * Null for what is not a plain variable: a file becomes a URL, a text or an
     * address never reaches the stylesheet, and the input border style decides
     * which edges the stylesheet draws.
     */
    public function cssVariable(): ?string
    {
        if (in_array($this->type, [
            RealmThemeSettingType::File,
            RealmThemeSettingType::Text,
            RealmThemeSettingType::Email,
            RealmThemeSettingType::InputBorderStyle,
        ], true)) {
            return null;
        }

        return str_starts_with($this->name, '--') ? $this->name : '--lock-'.$this->name;
    }
}
