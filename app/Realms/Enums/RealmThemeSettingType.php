<?php

declare(strict_types=1);

namespace App\Realms\Enums;

use App\Realms\Support\RealmThemeValue;

/**
 * A theme value reaches a stylesheet on the pages that collect credentials, so
 * the type decides the grammar {@see RealmThemeValue} holds
 * it to.
 */
enum RealmThemeSettingType: string
{
    case Color = 'color';
    case Length = 'length';
    case FontStack = 'font-stack';
    case FontShorthand = 'font-shorthand';
    case Shadow = 'shadow';
    case Gradient = 'gradient';
    case Filter = 'filter';
    case TextTransform = 'text-transform';
    case InputBorderStyle = 'input-border-style';
    case BackgroundFit = 'background-fit';
    case File = 'file';
    case Text = 'text';
    case Email = 'email';
}
