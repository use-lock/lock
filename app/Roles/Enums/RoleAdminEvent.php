<?php
declare(strict_types=1);

namespace App\Roles\Enums;

use App\Shared\Audit\Concerns\IsAdminEventType;
use App\Shared\Audit\Contracts\AdminEventType;

enum RoleAdminEvent: string implements AdminEventType
{
    use IsAdminEventType;

    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case AssignmentsUpdated = 'role.assignments-updated';
}
