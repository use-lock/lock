<?php

declare(strict_types=1);

namespace App\Realms\Enums;

/**
 * What the last domain check found. `Unreachable` means no HTTP answer at all
 * (DNS, connection, TLS); `Misrouted` means something answered, but not this
 * Lock instance.
 */
enum RealmDomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Unreachable = 'unreachable';
    case Misrouted = 'misrouted';
}
