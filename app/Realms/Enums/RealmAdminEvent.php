<?php
declare(strict_types=1);

namespace App\Realms\Enums;

use App\Shared\Audit\Concerns\IsAdminEventType;
use App\Shared\Audit\Contracts\AdminEventType;

enum RealmAdminEvent: string implements AdminEventType
{
    use IsAdminEventType;

    case RealmCreated = 'realm.created';
    case RealmUpdated = 'realm.updated';
    case RealmDeleted = 'realm.deleted';
    case SocialProviderCreated = 'realm.social-provider-created';
    case SocialProviderUpdated = 'realm.social-provider-updated';
    case SocialProviderDeleted = 'realm.social-provider-deleted';
}
