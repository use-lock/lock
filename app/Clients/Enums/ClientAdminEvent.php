<?php
declare(strict_types=1);

namespace App\Clients\Enums;

use App\Shared\Audit\Concerns\IsAdminEventType;
use App\Shared\Audit\Contracts\AdminEventType;

enum ClientAdminEvent: string implements AdminEventType
{
    use IsAdminEventType;

    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';
    case ClientSecretRevealed = 'client.secret-revealed';
    case ClientSecretRotated = 'client.secret-rotated';
    case ClientRevoked = 'client.revoked';
    case ClientRestored = 'client.restored';
    case ClientDeleted = 'client.deleted';
}
