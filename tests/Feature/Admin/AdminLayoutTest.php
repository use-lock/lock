<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Realms\Models\Realm;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;
use function Tests\Helpers\latticeSchema;
use function Tests\Helpers\menuItemHrefs;

it('drops the instance areas when the admin has only realm permissions', function () {
    $allowed = $this->actingAs(globalAdmin())->get(route('admin.realms'))->assertOk();

    expect(latticeSchema($allowed, 'props.lattice.layout.schema')->firstOfType('menu', 'admin-sidebar-instance-menu'))->not->toBeNull();

    $response = $this->actingAs(globalAdminWith(ManagementScope::UsersRead))->get(route('admin.realms.users', ['realm' => 'admin']))->assertOk();

    expect(latticeSchema($response, 'props.lattice.layout.schema')->firstOfType('menu', 'admin-sidebar-instance-menu'))->toBeNull();
});

it('turns the sidebar into the selected realm console, showing only what the admin may open', function (string $roleName, array $areas) {
    Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);

    $response = $this->actingAs(globalAdmin($roleName))->get(route('admin.realms.settings', ['realm' => 'acme']))->assertOk();

    expect(menuItemHrefs(latticeSchema($response, 'props.lattice.layout.schema')->firstOfTypeOrFail('menu', 'admin-sidebar-menu')))
        ->toBe(array_map(fn (string $area): string => "/admin/realms/acme/{$area}", $areas));
})->with([
    'super admin' => [ManagementRoles::SUPER_ADMIN, ['resources', 'clients', 'users', 'roles', 'user-events', 'settings']],
    'support admin' => [ManagementRoles::SUPPORT, ['users', 'roles', 'user-events', 'settings']],
]);
