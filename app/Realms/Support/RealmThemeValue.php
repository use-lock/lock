<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Enums\RealmThemeSettingType;
use App\Realms\Models\RealmThemeAsset;

/**
 * A value ends up in a stylesheet on the pages that collect credentials, so each
 * type is an allowlist rather than a blocklist: a value that does not match
 * cannot close a declaration, load a URL or reach a variable it should not.
 */
final class RealmThemeValue
{
    public const string ASSET_PREFIX = 'asset:';

    private const int MAX_LENGTH = 1000;

    private const string LENGTH = '(?:-?(?:\d+\.?\d*|\.\d+)(?:px|rem|em|%|vh|vw|dvh)|0)';

    private const string COLOR_FUNCTION = '(?:rgba?|hsla?|oklch|oklab)\([0-9a-z .,%\/-]+\)';

    private const string COLOR = '(?:#[0-9a-fA-F]{3,8}|'.self::COLOR_FUNCTION.')';

    private const string FONT_NAME = '(?:\'[A-Za-z0-9 _-]+\'|"[A-Za-z0-9 _-]+"|[A-Za-z][A-Za-z0-9-]*(?: [A-Za-z][A-Za-z0-9-]*)*)';

    private const string FONT_STACK = self::FONT_NAME.'(?:\s*,\s*'.self::FONT_NAME.')*';

    private const string SHADOW_LAYER = '(?:inset\s+)?(?:'.self::LENGTH.'\s+){1,4}'.self::COLOR;

    private const string GRADIENT = '(?:linear|radial)-gradient\((?:[0-9a-zA-Z .,%\/#-]|'.self::COLOR_FUNCTION.')+\)';

    private const string FILTER_FUNCTION = '(?:brightness|contrast|grayscale|saturate|sepia|opacity|blur)\([0-9.]+(?:%|px)?\)';

    public const string FAMILY = '/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,63}\z/';

    public static function accepts(RealmThemeSettingType $type, string $value): bool
    {
        if (strlen($value) > self::MAX_LENGTH) {
            return false;
        }

        return match ($type) {
            RealmThemeSettingType::Color => self::matches(self::COLOR, $value),
            RealmThemeSettingType::Length => self::matches(self::LENGTH, $value),
            RealmThemeSettingType::FontStack => self::matches(self::FONT_STACK, $value),
            RealmThemeSettingType::FontShorthand => self::matches(
                '(?:[1-9]00|normal|bold)\s+\d*\.?\d+(?:px|rem|em)(?:\/\d*\.?\d+(?:px|rem|em)?)?\s+(?:var\(--lt-font-(?:sans|display|mono)\)|'.self::FONT_STACK.')',
                $value,
            ),
            RealmThemeSettingType::Shadow => self::matches('none|'.self::SHADOW_LAYER.'(?:\s*,\s*'.self::SHADOW_LAYER.')*', $value),
            RealmThemeSettingType::Gradient => self::matches('none|'.self::GRADIENT.'(?:\s*,\s*'.self::GRADIENT.')*', $value),
            RealmThemeSettingType::Filter => self::matches('none|'.self::FILTER_FUNCTION.'(?:\s+'.self::FILTER_FUNCTION.')*', $value),
            RealmThemeSettingType::TextTransform, RealmThemeSettingType::InputBorderStyle, RealmThemeSettingType::BackgroundFit => in_array($value, self::options($type), true),
            RealmThemeSettingType::File => self::isFile($value),
            RealmThemeSettingType::Text => $value !== '' && strlen($value) <= 255 && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1,
            RealmThemeSettingType::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
        };
    }

    public static function isFile(string $value): bool
    {
        if (str_starts_with($value, self::ASSET_PREFIX)) {
            return RealmThemeAsset::isValidName(substr($value, strlen(self::ASSET_PREFIX)));
        }

        return preg_match('/\Ahttps:\/\/[A-Za-z0-9.-]+(?::\d+)?(?:\/[A-Za-z0-9._~%\/+-]*)?(?:\?[A-Za-z0-9._~%&=+-]*)?\z/', $value) === 1;
    }

    /**
     * @return list<string>
     */
    public static function options(RealmThemeSettingType $type): array
    {
        return match ($type) {
            RealmThemeSettingType::TextTransform => ['none', 'uppercase', 'lowercase', 'capitalize'],
            RealmThemeSettingType::InputBorderStyle => ['box', 'underline'],
            RealmThemeSettingType::BackgroundFit => ['cover', 'contain'],
            default => [],
        };
    }

    private static function matches(string $pattern, string $value): bool
    {
        return preg_match('/\A(?:'.$pattern.')\z/', $value) === 1;
    }
}
