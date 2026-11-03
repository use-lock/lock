<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Data\RealmTheme;
use App\Realms\Data\RealmThemeFontFace;
use App\Realms\Enums\RealmAppearance;

/**
 * Writes the variables `resources/css/realm-theme.css` reads. They stay unlayered
 * so they beat the values Lattice declares inside its cascade layer in both
 * modes, and every value is checked once more: this is the last step before raw
 * output on a page that collects credentials.
 */
final class RealmThemeStylesheet
{
    public static function render(RealmTheme $theme, RealmThemeFiles $files): string
    {
        $rules = array_filter(array_map(
            fn (RealmThemeFontFace $face): ?string => self::fontFace($face, $files),
            $theme->fontFaces,
        ));

        $rules[] = ':root[data-realm-theme] { '.implode(' ', self::declarations($theme, $files)).' }';

        if ($theme->value('--lt-bg') !== null) {
            $rules[] = 'html[data-realm-theme], html.dark[data-realm-theme] { background-color: var(--lt-bg); }';
        }

        return implode("\n", $rules);
    }

    /**
     * @return list<string>
     */
    private static function declarations(RealmTheme $theme, RealmThemeFiles $files): array
    {
        $declarations = $theme->appearance === RealmAppearance::System ? [] : ['color-scheme: '.$theme->appearance->value.';'];

        foreach (RealmThemeConfiguration::settings() as $setting) {
            $value = $theme->value($setting->name);
            $variable = $setting->cssVariable();

            if ($variable !== null && $value !== null && RealmThemeValue::accepts($setting->type, $value)) {
                $declarations[] = $variable.': '.$value.';';
            }
        }

        $background = self::fileUrl($theme->value('background-image-url'), $files);

        if ($background !== null) {
            $declarations[] = '--lock-background-image: url("'.$background.'");';
        }

        if ($theme->value('input-border-style') === 'underline') {
            $declarations[] = '--lock-input-border-edge: 0 0 var(--lock-input-border-width, 1px) 0;';
            $declarations[] = '--lock-input-radius: var(--lt-radius-sm) var(--lt-radius-sm) 0 0;';
            $declarations[] = '--lock-input-shadow: none;';
        }

        if ($theme->value('--lt-control-h-lg') !== null) {
            $declarations[] = '--lock-button-height: var(--lt-control-h-lg);';
        }

        if ($theme->value('text-shadow-small') !== null) {
            $declarations[] = '--lock-status-dot: inline-block;';
        }

        return $declarations;
    }

    private static function fontFace(RealmThemeFontFace $face, RealmThemeFiles $files): ?string
    {
        $url = self::fileUrl($face->file, $files);

        if ($url === null || preg_match(RealmThemeValue::FAMILY, $face->family) !== 1 || ! in_array($face->style, ['normal', 'italic'], true)) {
            return null;
        }

        return "@font-face { font-family: '{$face->family}'; src: url(\"{$url}\"); font-weight: {$face->weight}; font-style: {$face->style}; font-display: swap; }";
    }

    private static function fileUrl(?string $value, RealmThemeFiles $files): ?string
    {
        return $value !== null && RealmThemeValue::isFile($value) ? $files->url($value) : null;
    }
}
