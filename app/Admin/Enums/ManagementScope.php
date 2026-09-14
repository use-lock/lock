<?php
declare(strict_types=1);

namespace App\Admin\Enums;

/**
 * The only authorization currency Lock has. A token carries them, a role grants
 * them, and the console and `EnsureScopes` read the same values — which is why
 * they live here and not with the management API whose resource carries their
 * rows. There is no implication between them: a role that may write also lists
 * the matching read scope.
 */
enum ManagementScope: string
{
    case RealmsRead = 'realms:read';

    case RealmsWrite = 'realms:write';

    case UsersRead = 'users:read';

    case UsersWrite = 'users:write';

    case ClientsRead = 'clients:read';

    case ClientsWrite = 'clients:write';

    case RolesRead = 'roles:read';

    case RolesWrite = 'roles:write';

    case ResourcesRead = 'resources:read';

    case ResourcesWrite = 'resources:write';

    case AdminEventsRead = 'admin-events:read';

    case UserEventsRead = 'user-events:read';

    public function description(): string
    {
        return match ($this) {
            self::RealmsRead => 'Read realms, their settings and their domain state.',
            self::RealmsWrite => 'Create, rename, re-point, configure and delete realms.',
            self::UsersRead => 'Read the users of a realm and their sessions.',
            self::UsersWrite => 'Create, edit, block, delete users and end their sessions.',
            self::ClientsRead => 'Read the OAuth clients of a realm.',
            self::ClientsWrite => 'Create, edit, revoke and delete OAuth clients.',
            self::RolesRead => 'Read the roles of a realm and their scopes.',
            self::RolesWrite => 'Create, edit and delete roles, and assign them to users.',
            self::ResourcesRead => 'Read the protected resources of a realm and their scopes.',
            self::ResourcesWrite => 'Create, edit and delete protected resources and their scopes.',
            self::AdminEventsRead => 'Read the trail of what administrators changed.',
            self::UserEventsRead => 'Read the sign-in, multi-factor and token events of a realm.',
        };
    }

    /** @return array<string, string> */
    public static function catalog(): array
    {
        $catalog = [];

        foreach (self::cases() as $scope) {
            $catalog[$scope->value] = $scope->description();
        }

        return $catalog;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
