<?php

declare(strict_types=1);

namespace App\Realms\Enums;

/**
 * The stored `int` does not tell a duration, a day count and a plain count
 * apart, so the type is declared.
 */
enum RealmSettingType
{
    case Duration;
    case Days;
    case Count;
    case Text;
    case Flag;
    case StringList;
    case Factors;
    case LoginMethods;
    case MfaRequirement;
}
