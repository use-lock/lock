<?php
declare(strict_types=1);

namespace App\Resources\Enums;

use App\Shared\Audit\Concerns\IsAdminEventType;
use App\Shared\Audit\Contracts\AdminEventType;

enum ResourceAdminEvent: string implements AdminEventType
{
    use IsAdminEventType;

    case ResourceCreated = 'resource.created';
    case ResourceUpdated = 'resource.updated';
    case ResourceDeleted = 'resource.deleted';
    case ScopeCreated = 'resource.scope.created';
    case ScopeUpdated = 'resource.scope.updated';
    case ScopeDeleted = 'resource.scope.deleted';
}
