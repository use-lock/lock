<?php
declare(strict_types=1);

namespace App\Admin;

use App\Admin\Enums\ManagementScope;

/**
 * The roles Lock ships with: realm roles of the master realm whose scopes are
 * the management API's. They are ordinary roles otherwise — the console lists
 * and assigns them like any other — but `app:bootstrap` owns their scope sets,
 * so nothing else may rename or delete them.
 *
 * There is no implication between a read and a write scope, so a role that may
 * write lists both.
 */
final class ManagementRoles
{
    public const string SUPER_ADMIN = 'Super Admin';

    public const string SUPPORT = 'Support';

    /**
     * @return array<string, list<ManagementScope>>
     */
    public static function grants(): array
    {
        return [
            self::SUPER_ADMIN => ManagementScope::cases(),
            self::SUPPORT => [
                ManagementScope::RealmsRead,
                ManagementScope::UsersRead,
                ManagementScope::UsersWrite,
                ManagementScope::RolesRead,
                ManagementScope::UserEventsRead,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::grants());
    }
}
