<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;

function globalAdmin(string $roleName = ManagementRoles::SUPER_ADMIN): User
{
    $admin = User::factory()->for(Realm::master())->create();

    grantGlobalRole($admin, $roleName);

    return $admin;
}

function grantGlobalRole(User $user, string $name): void
{
    $user->roles()->attach(Realm::master()->roles()->where('name', $name)->sole());
}

/**
 * A throwaway master realm role holding exactly these management scopes.
 */
function globalAdminWith(ManagementScope ...$scopes): User
{
    $realm = Realm::master();
    $admin = User::factory()->for($realm)->create();
    $role = Role::factory()->for($realm)->create();

    $role->scopes()->sync(managementScopeIds(...$scopes));
    $admin->roles()->attach($role);

    return $admin;
}

/**
 * @return list<string>
 */
function managementScopeIds(ManagementScope ...$scopes): array
{
    $realm = Realm::master();

    return array_values(array_map(fn (ManagementScope $scope): string => $realm->realmResources()
        ->where('identifier', $scope->apiResource()->value)
        ->sole()->scopes()->where('value', $scope->value)->sole()->getKey(), $scopes));
}
