<?php

declare(strict_types=1);

namespace App\Realms\Enums;

enum RealmThemeSection: string
{
    case Identity = 'identity';
    case Colors = 'colors';
    case Shape = 'shape';
    case Typography = 'typography';
    case Background = 'background';
    case Panel = 'panel';
    case Buttons = 'buttons';
    case Inputs = 'inputs';
    case Links = 'links';
    case Mail = 'mail';
}
