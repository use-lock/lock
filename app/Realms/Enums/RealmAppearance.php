<?php

declare(strict_types=1);

namespace App\Realms\Enums;

enum RealmAppearance: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
}
