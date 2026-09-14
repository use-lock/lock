<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Roles\Models\Role;
use Illuminate\Support\Facades\Gate;

use function Tests\Helpers\grantGlobalRole;
use function Tests\Helpers\managementScopeIds;

test('super admin passes every scope gate', function () {
    $user = User::factory()->for(Realm::master())->create();
    grantGlobalRole($user, ManagementRoles::SUPER_ADMIN);

    foreach (ManagementScope::cases() as $scope) {
        expect(Gate::forUser($user)->allows($scope))->toBeTrue();
    }
});

test('support holds exactly the scopes its grant lists', function () {
    $user = User::factory()->for(Realm::master())->create();
    grantGlobalRole($user, ManagementRoles::SUPPORT);

    $granted = [
        ManagementScope::RealmsRead,
        ManagementScope::UsersRead,
        ManagementScope::UsersWrite,
        ManagementScope::RolesRead,
        ManagementScope::UserEventsRead,
    ];

    foreach (ManagementScope::cases() as $scope) {
        expect(Gate::forUser($user)->allows($scope))->toBe(in_array($scope, $granted, true));
    }
});

test('a write scope does not imply its read counterpart', function () {
    $user = User::factory()->for(Realm::master())->create();
    $role = Role::factory()->for(Realm::master())->create();
    $role->scopes()->sync(managementScopeIds(ManagementScope::RealmsWrite));
    $user->roles()->attach($role);

    expect($user->can(ManagementScope::RealmsWrite))->toBeTrue()
        ->and($user->can(ManagementScope::RealmsRead))->toBeFalse();
});

test('a scope check reflects a role attached after an earlier check, once the user is refreshed', function () {
    $user = User::factory()->for(Realm::master())->create();
    expect($user->can(ManagementScope::RealmsRead))->toBeFalse();

    grantGlobalRole($user, ManagementRoles::SUPER_ADMIN);

    expect($user->refresh()->can(ManagementScope::RealmsRead))->toBeTrue();
});

test('a role outside the master realm grants nothing, whatever its scopes read', function () {
    $realm = Realm::factory()->create();
    $user = User::factory()->for($realm)->create();
    $role = Role::factory()->for($realm)->create();
    $resource = Resource::factory()->for($realm)->create(['identifier' => 'api']);
    $scope = $resource->scopes()->create(['value' => ManagementScope::RealmsWrite->value]);

    $role->scopes()->sync([$scope->id]);
    $user->roles()->attach($role);

    foreach (ManagementScope::cases() as $case) {
        expect(Gate::forUser($user)->allows($case))->toBeFalse();
    }
});
