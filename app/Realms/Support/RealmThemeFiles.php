<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Data\RealmThemeFontFace;
use App\Realms\Enums\RealmThemeSettingType;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;

/**
 * Resolves the file settings of one realm. A reference to a file that is not
 * there is not an error: a Pulumi program uploads the files after the realm that
 * names them exists, so it has to be able to name them first.
 */
final readonly class RealmThemeFiles
{
    /**
     * @param  array<string, RealmThemeAsset>  $assets
     */
    private function __construct(private Realm $realm, private array $assets) {}

    public static function for(Realm $realm): self
    {
        return new self($realm, $realm->themeAssets()->get()->keyBy('name')->all());
    }

    public function url(?string $value): ?string
    {
        if ($value === null || ! str_starts_with($value, RealmThemeValue::ASSET_PREFIX)) {
            return $value;
        }

        $asset = $this->assets[substr($value, strlen(RealmThemeValue::ASSET_PREFIX))] ?? null;

        return $asset?->path();
    }

    public function absoluteUrl(?string $value): ?string
    {
        $url = $this->url($value);

        return $url !== null && str_starts_with($url, '/') ? $this->realm->origin().$url : $url;
    }

    /**
     * @return list<string>
     */
    public function missing(): array
    {
        $theme = $this->realm->theme;

        $references = [
            ...array_map(fn (RealmThemeFontFace $face): string => $face->file, $theme->fontFaces),
            ...array_values(array_filter(
                array_map(
                    fn (string $name): ?string => RealmThemeConfiguration::setting($name)?->type === RealmThemeSettingType::File ? $theme->value($name) : null,
                    array_keys($theme->values),
                ),
                is_string(...),
            )),
        ];

        $missing = array_filter(
            $references,
            fn (string $reference): bool => str_starts_with($reference, RealmThemeValue::ASSET_PREFIX) && $this->url($reference) === null,
        );

        return array_values(array_unique(array_map(
            fn (string $reference): string => substr($reference, strlen(RealmThemeValue::ASSET_PREFIX)),
            $missing,
        )));
    }
}
