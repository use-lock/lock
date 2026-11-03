<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Data\RealmTheme;
use App\Realms\Data\RealmThemeFontFace;
use App\Realms\Data\RealmThemePatch;
use App\Realms\Data\RealmThemeSetting;
use App\Realms\Enums\RealmAppearance;
use App\Realms\Enums\RealmThemeSection;
use App\Realms\Enums\RealmThemeSettingType as Type;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final class RealmThemeConfiguration
{
    /**
     * @return array<string, RealmThemeSetting>
     */
    public static function settings(): array
    {
        $settings = [];

        foreach (self::sectionTypes() as $section => $types) {
            foreach ($types as $name => $type) {
                $settings[$name] = new RealmThemeSetting($name, RealmThemeSection::from($section), $type);
            }
        }

        return $settings;
    }

    public static function setting(string $name): ?RealmThemeSetting
    {
        return self::settings()[$name] ?? null;
    }

    /**
     * @return list<RealmThemeSetting>
     */
    public static function section(RealmThemeSection $section): array
    {
        return array_values(array_filter(
            self::settings(),
            fn (RealmThemeSetting $setting): bool => $setting->section === $section,
        ));
    }

    /**
     * Every caller reaches this, the API and the console alike, so nothing gets
     * into a theme without passing the grammar of its setting.
     *
     * @throws ValidationException
     */
    public static function apply(RealmTheme $theme, RealmThemePatch $patch): RealmTheme
    {
        $errors = [];

        $appearance = $theme->appearance;

        if (! $patch->appearance instanceof Optional) {
            $appearance = RealmAppearance::tryFrom($patch->appearance);

            if ($appearance === null) {
                $errors['theme.appearance'] = [__('realms.theme.errors.appearance')];
            }
        }

        $values = $theme->values;

        if (! $patch->values instanceof Optional) {
            foreach ($patch->values as $name => $value) {
                $key = 'theme.values.'.$name;
                $setting = self::setting((string) $name);

                if ($setting === null) {
                    $errors[$key] = [__('realms.theme.errors.unknown', ['setting' => $name])];
                } elseif ($value === null || $value === '') {
                    unset($values[$name]);
                } elseif (! is_string($value) || ! RealmThemeValue::accepts($setting->type, $value)) {
                    $errors[$key] = [__('realms.theme.types.'.$setting->type->value)];
                } else {
                    $values[$name] = $value;
                }
            }
        }

        $fontFaces = $theme->fontFaces;

        if (! $patch->fontFaces instanceof Optional) {
            $fontFaces = array_values($patch->fontFaces);

            foreach ($fontFaces as $index => $face) {
                $errors = [...$errors, ...self::fontFaceErrors($face, $index)];
            }
        }

        if ($errors !== [] || $appearance === null) {
            throw ValidationException::withMessages($errors);
        }

        ksort($values);

        return new RealmTheme($appearance, $values, $fontFaces);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function fontFaceErrors(RealmThemeFontFace $face, int $index): array
    {
        $key = 'theme.font_faces.'.$index;

        return array_filter([
            $key.'.family' => preg_match(RealmThemeValue::FAMILY, $face->family) === 1 ? [] : [__('realms.theme.errors.family')],
            $key.'.file' => RealmThemeValue::isFile($face->file) ? [] : [__('realms.theme.types.file')],
            $key.'.weight' => $face->weight >= 1 && $face->weight <= 1000 ? [] : [__('realms.theme.errors.weight')],
            $key.'.style' => in_array($face->style, ['normal', 'italic'], true) ? [] : [__('realms.theme.errors.style')],
        ]);
    }

    /**
     * @return array<string, array<string, Type>>
     */
    private static function sectionTypes(): array
    {
        return [
            'identity' => [
                'logo-url' => Type::File,
                'logo-alt' => Type::Text,
                'logo-width' => Type::Length,
                'favicon-url' => Type::File,
            ],
            'colors' => [
                '--lt-bg' => Type::Color,
                '--lt-fg' => Type::Color,
                '--lt-surface' => Type::Color,
                '--lt-surface-fg' => Type::Color,
                '--lt-popover' => Type::Color,
                '--lt-popover-fg' => Type::Color,
                '--lt-primary' => Type::Color,
                '--lt-primary-fg' => Type::Color,
                '--lt-primary-hover' => Type::Color,
                '--lt-primary-active' => Type::Color,
                '--lt-secondary' => Type::Color,
                '--lt-secondary-fg' => Type::Color,
                '--lt-secondary-hover' => Type::Color,
                '--lt-secondary-active' => Type::Color,
                '--lt-muted' => Type::Color,
                '--lt-muted-fg' => Type::Color,
                '--lt-accent' => Type::Color,
                '--lt-accent-fg' => Type::Color,
                '--lt-danger' => Type::Color,
                '--lt-danger-fg' => Type::Color,
                '--lt-warning' => Type::Color,
                '--lt-warning-fg' => Type::Color,
                '--lt-success' => Type::Color,
                '--lt-success-fg' => Type::Color,
                '--lt-info' => Type::Color,
                '--lt-info-fg' => Type::Color,
                '--lt-disabled' => Type::Color,
                '--lt-disabled-fg' => Type::Color,
                '--lt-border' => Type::Color,
                '--lt-input' => Type::Color,
                '--lt-ring' => Type::Color,
                '--lt-overlay' => Type::Color,
            ],
            'shape' => [
                '--lt-radius' => Type::Length,
                '--lt-radius-xs' => Type::Length,
                '--lt-radius-sm' => Type::Length,
                '--lt-radius-lg' => Type::Length,
                '--lt-radius-xl' => Type::Length,
                '--lt-ring-width' => Type::Length,
                '--lt-ring-offset' => Type::Length,
                '--lt-control-h-md' => Type::Length,
                '--lt-control-h-lg' => Type::Length,
                '--lt-input-font-size-mobile' => Type::Length,
                '--lt-shadow-inner' => Type::Shadow,
            ],
            'typography' => [
                '--lt-font-sans' => Type::FontStack,
                '--lt-font-display' => Type::FontStack,
                '--lt-font-mono' => Type::FontStack,
                'heading-size' => Type::Length,
                'heading-tracking' => Type::Length,
                'text-shadow-heading' => Type::Shadow,
                'text-shadow-small' => Type::Shadow,
                'label-font' => Type::FontShorthand,
                'label-tracking' => Type::Length,
                'label-transform' => Type::TextTransform,
                'label-color' => Type::Color,
            ],
            'background' => [
                'background-image-url' => Type::File,
                'background-color' => Type::Color,
                'background-fit' => Type::BackgroundFit,
                'background-scrim' => Type::Gradient,
            ],
            'panel' => [
                'panel-border-width' => Type::Length,
                'panel-blur' => Type::Length,
                'panel-padding' => Type::Length,
                'panel-width' => Type::Length,
            ],
            'buttons' => [
                'button-primary-fill' => Type::Gradient,
                'button-primary-border' => Type::Color,
                'button-primary-shadow' => Type::Shadow,
                'button-primary-shadow-hover' => Type::Shadow,
                'button-hover-filter' => Type::Filter,
                'button-active-filter' => Type::Filter,
                'button-disabled-filter' => Type::Filter,
                'button-disabled-shadow' => Type::Shadow,
                'button-font-size' => Type::Length,
                'button-tracking' => Type::Length,
                'button-transform' => Type::TextTransform,
                'button-padding-x' => Type::Length,
                'button-secondary-border' => Type::Color,
                'button-secondary-border-hover' => Type::Color,
                'button-secondary-shadow' => Type::Shadow,
                'button-secondary-blur' => Type::Length,
            ],
            'inputs' => [
                'input-background' => Type::Color,
                'input-background-focus' => Type::Color,
                'input-background-error' => Type::Color,
                'input-border-style' => Type::InputBorderStyle,
                'input-border-width' => Type::Length,
                'input-border-focus' => Type::Color,
                'input-caret' => Type::Color,
                'input-placeholder' => Type::Color,
                'input-font-size' => Type::Length,
            ],
            'links' => [
                'link-inline-color' => Type::Color,
                'link-inline-hover' => Type::Color,
                'link-action-font' => Type::FontShorthand,
                'link-action-tracking' => Type::Length,
            ],
            'mail' => [
                'mail-logo-url' => Type::File,
                'mail-from-name' => Type::Text,
                'mail-from-address' => Type::Email,
            ],
        ];
    }
}
