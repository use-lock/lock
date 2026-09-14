<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Audit\Models\AdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Actions\CreateRole;
use App\Roles\Actions\DeleteRole;
use App\Roles\Actions\SyncUserRoles;
use App\Roles\Actions\UpdateRole;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Roles\Ui\Actions\DeleteRoleAction;
use App\Roles\Ui\Actions\UpdateRoleAction;
use App\Roles\Ui\Actions\UpdateUserRoles;
use App\Roles\Ui\Forms\CreateRoleForm;
use App\Roles\Ui\Tables\RolesTable;
use Illuminate\Validation\ValidationException;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->other = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex']);
});

test('a roles-write admin creates a realm role and it is recorded', function () {
    $this->actingAs(globalAdmin())->submitForm(CreateRoleForm::class, [
        'name' => 'member',
        'description' => '  Standard access  ',
    ], ['realm' => 'acme'])->assertRedirect();

    $role = $this->realm->roles()->sole();

    expect($role->name)->toBe('member')
        ->and($role->description)->toBe('Standard access')
        ->and($role->realm_id)->toBe($this->realm->id);

    $event = AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::RoleCreated->type())->sole();

    expect($event->subject_id)->toBe($role->id)
        ->and(data_get($event->context, 'realm'))->toBe('acme')
        ->and(data_get($event->context, 'name'))->toBe('member');
});

test('a realm role name is unique per realm but free in another', function () {
    Role::factory()->for($this->realm)->create(['name' => 'member']);

    $this->actingAs(globalAdmin());
    $this->submitForm(CreateRoleForm::class, ['name' => 'member'], ['realm' => 'acme'])->assertInvalid(['name']);
    $this->submitForm(CreateRoleForm::class, ['name' => 'member'], ['realm' => 'globex'])->assertRedirect();

    expect(Role::query()->where('name', 'member')->whereIn('realm_id', [$this->realm->id, $this->other->id])->count())->toBe(2);
});

test('a realm role name is rejected when it is not token safe', function (string $name) {
    $this->actingAs(globalAdmin())
        ->submitForm(CreateRoleForm::class, ['name' => $name], ['realm' => 'acme'])
        ->assertInvalid(['name']);

    expect($this->realm->roles()->exists())->toBeFalse();
})->with([
    'whitespace' => 'realm admin',
    'leading punctuation' => '-admin',
    'quotes' => 'ad"min',
]);

test('renaming a realm role records the change and renames it on its users', function () {
    $role = Role::factory()->for($this->realm)->create(['name' => 'member', 'description' => null]);
    $user = User::factory()->for($this->realm)->create();
    $user->roles()->attach($role);

    $this->actingAs(globalAdmin())->callAction(UpdateRoleAction::class, [
        'name' => 'staff',
        'description' => 'Renamed',
    ], ['realm' => 'acme', 'role' => $role->id])->assertOk();

    expect($user->refresh()->roleNames())->toBe(['staff']);

    $event = AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::RoleUpdated->type())->sole();

    expect(data_get($event->context, 'changes'))->toBe([
        'name' => ['old' => 'member', 'new' => 'staff'],
        'description' => ['old' => null, 'new' => 'Renamed'],
    ]);
});

test('deleting a realm role detaches every user that holds it', function () {
    $role = Role::factory()->for($this->realm)->create(['name' => 'member']);
    $kept = Role::factory()->for($this->realm)->create(['name' => 'admin']);
    $user = User::factory()->for($this->realm)->create();
    $user->roles()->attach([$role->id, $kept->id]);

    $this->actingAs(globalAdmin())
        ->callAction(DeleteRoleAction::class, [], ['realm' => 'acme', 'role' => $role->id])
        ->assertOk();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and($user->refresh()->roleNames())->toBe(['admin']);

    $event = AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::RoleDeleted->type())->sole();

    expect(data_get($event->context, 'name'))->toBe('member')
        ->and(data_get($event->context, 'users-detached'))->toBe(1);
});

test('the roles table lists only the roles of the selected realm with their user counts', function () {
    $member = Role::factory()->for($this->realm)->create(['name' => 'member']);
    Role::factory()->for($this->realm)->create(['name' => 'admin']);
    Role::factory()->for($this->other)->create(['name' => 'elsewhere']);
    User::factory()->for($this->realm)->create()->roles()->attach($member);

    $rows = $this->actingAs(globalAdmin())
        ->loadTable(RolesTable::class, context: ['realm' => 'acme'])
        ->assertOk()
        ->json('data');

    expect(collect(is_array($rows) ? $rows : [])->pluck('name')->all())->toBe(['admin', 'member'])
        ->and(collect(is_array($rows) ? $rows : [])->firstWhere('name', 'member')['users_count'])->toBe(1);
});

test('a realm role is only reachable through its own realm', function () {
    $role = Role::factory()->for($this->realm)->create(['name' => 'member']);
    $foreign = ['realm' => 'globex', 'role' => $role->id];

    $this->actingAs(globalAdmin());
    $this->callDeniedAction(UpdateRoleAction::class, ['name' => 'renamed'], $foreign)->assertForbidden();
    $this->callDeniedAction(DeleteRoleAction::class, [], $foreign)->assertForbidden();

    expect($role->refresh()->name)->toBe('member');
});

test('the roles console is forbidden without a roles scope', function () {
    $role = Role::factory()->for($this->realm)->create(['name' => 'member']);
    $context = ['realm' => 'acme', 'role' => $role->id];

    $this->actingAs(globalAdminWith(ManagementScope::AdminEventsRead));
    $this->get('/admin/realms/acme/roles')->assertForbidden();
    $this->loadDeniedTable(RolesTable::class, context: ['realm' => 'acme'])->assertForbidden();
    $this->submitDeniedForm(CreateRoleForm::class, ['name' => 'other'], ['realm' => 'acme'])->assertForbidden();
    $this->callDeniedAction(UpdateRoleAction::class, ['name' => 'other'], $context)->assertForbidden();
    $this->callDeniedAction(DeleteRoleAction::class, [], $context)->assertForbidden();

    expect($this->realm->roles()->count())->toBe(1);
});

test('assigning realm roles to a user replaces the set and records what changed', function () {
    $member = Role::factory()->for($this->realm)->create(['name' => 'member']);
    $admin = Role::factory()->for($this->realm)->create(['name' => 'admin']);
    $user = User::factory()->for($this->realm)->create();
    $user->roles()->attach($member);

    $this->actingAs(globalAdmin())->callAction(UpdateUserRoles::class, [
        'role_ids' => [$admin->id],
    ], ['realm' => 'acme', 'user' => $user->id])->assertOk();

    expect($user->refresh()->roleNames())->toBe(['admin']);

    $event = AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::AssignmentsUpdated->type())->sole();

    expect($event->subject_id)->toBe($user->id)
        ->and(data_get($event->context, 'added'))->toBe(['admin'])
        ->and(data_get($event->context, 'removed'))->toBe(['member']);
});

test('a role of another realm cannot be assigned to a user', function () {
    $foreign = Role::factory()->for($this->other)->create(['name' => 'elsewhere']);
    $user = User::factory()->for($this->realm)->create();

    $this->actingAs(globalAdmin())->callAction(UpdateUserRoles::class, [
        'role_ids' => [$foreign->id],
    ], ['realm' => 'acme', 'user' => $user->id])->assertInvalid(['role_ids']);

    expect($user->refresh()->roles()->exists())->toBeFalse();
});

test('the role sync action itself refuses a role outside the user\'s realm', function () {
    $foreign = Role::factory()->for($this->other)->create(['name' => 'elsewhere']);
    $user = User::factory()->for($this->realm)->create();

    expect(fn () => app(SyncUserRoles::class)->handle($user, [$foreign->id]))
        ->toThrow(InvalidArgumentException::class)
        ->and($user->roles()->exists())->toBeFalse();
});

test('granting and clearing master realm roles opens and closes the console', function () {
    $target = User::factory()->for(Realm::master())->create();
    $superAdmin = Realm::master()->roles()->where('name', ManagementRoles::SUPER_ADMIN)->sole();
    $context = ['realm' => Realm::master()->slug, 'user' => $target->id];

    $this->actingAs(globalAdmin())->callAction(UpdateUserRoles::class, ['role_ids' => [$superAdmin->id]], $context)->assertOk();

    expect($target->refresh()->can(ManagementScope::RealmsWrite))->toBeTrue();

    $this->callAction(UpdateUserRoles::class, ['role_ids' => []], $context)->assertOk();

    expect($target->refresh()->managementScopes()->all())->toBeEmpty();
});

test('a role update that changes nothing is not logged', function () {
    $target = User::factory()->for(Realm::master())->create();

    $this->actingAs(globalAdmin())->callAction(UpdateUserRoles::class, [
        'role_ids' => [],
    ], ['realm' => Realm::master()->slug, 'user' => $target->id])->assertOk();

    expect(AdminEvent::global()->count())->toBe(0);
});

test('an admin cannot change their own roles', function () {
    $admin = globalAdmin();

    $this->actingAs($admin)->callDeniedAction(UpdateUserRoles::class, [
        'role_ids' => [],
    ], ['realm' => Realm::master()->slug, 'user' => $admin->id])->assertForbidden();
});

test('support cannot manage roles', function () {
    $support = globalAdmin(ManagementRoles::SUPPORT);
    $target = User::factory()->for(Realm::master())->create();

    $this->actingAs($support)->callDeniedAction(UpdateUserRoles::class, [
        'role_ids' => [],
    ], ['realm' => Realm::master()->slug, 'user' => $target->id])->assertForbidden();
});

test('creating a role through its page form assigns scopes and opens its detail page', function () {
    $resource = Resource::factory()->for($this->realm)->create();
    $scope = ResourceScope::factory()->for($resource)->create();

    $this->actingAs(globalAdmin())->submitForm(CreateRoleForm::class, [
        'name' => 'editor',
        'description' => 'Edit content',
        'scope_ids' => [$scope->id],
    ], ['realm' => 'acme'])->assertRedirect();

    $role = $this->realm->roles()->where('name', 'editor')->sole();

    expect($role->scopes()->pluck('resource_scopes.id')->all())->toBe([$scope->id]);

    $row = $this->loadTable(RolesTable::class, context: ['realm' => 'acme'])->assertOk()->row($role->id);

    expect($row->clickHref())->toBe("/admin/realms/acme/roles/{$role->id}")
        ->and($row->actionIds())->toBe(['admin.realm-roles.update', 'admin.realm-roles.delete']);

    $this->get("/admin/realms/acme/roles/{$role->id}")->assertOk();
    $this->get("/admin/realms/globex/roles/{$role->id}")->assertNotFound();
});

test('the role creation form rejects scopes from another realm', function () {
    $resource = Resource::factory()->for($this->other)->create();
    $scope = ResourceScope::factory()->for($resource)->create();

    $this->actingAs(globalAdmin())->submitForm(CreateRoleForm::class, [
        'name' => 'editor',
        'scope_ids' => [$scope->id],
    ], ['realm' => 'acme'])->assertInvalid(['scope_ids']);

    expect($this->realm->roles()->where('name', 'editor')->exists())->toBeFalse();
});

test('role readers can open details but cannot create edit or delete roles', function () {
    $role = Role::factory()->for($this->realm)->create();
    $this->actingAs(globalAdminWith(ManagementScope::RolesRead));
    $this->get("/admin/realms/acme/roles/{$role->id}")->assertOk();
    $this->get('/admin/realms/acme/roles/create')->assertForbidden();
    $this->submitDeniedForm(CreateRoleForm::class, ['name' => 'editor'], ['realm' => 'acme'])->assertForbidden();

    $row = $this->loadTable(RolesTable::class, context: ['realm' => 'acme'])->assertOk()->row($role->id);

    expect($row->actionIds())->toBeEmpty();
});

test('protected roles have readable detail pages without mutable row actions', function () {
    $realm = Realm::master();
    $role = $realm->roles()->where('name', ManagementRoles::SUPER_ADMIN)->sole();
    $this->actingAs(globalAdmin());
    $this->get("/admin/realms/{$realm->slug}/roles/{$role->id}")->assertOk();

    $row = $this->loadTable(RolesTable::class, context: ['realm' => $realm->slug])->assertOk()->row($role->id);

    expect($row->actionIds())->toBeEmpty();

    $this->callDeniedAction(UpdateRoleAction::class, ['name' => 'renamed'], ['realm' => $realm->slug, 'role' => $role->id])->assertForbidden();
    $this->callDeniedAction(DeleteRoleAction::class, [], ['realm' => $realm->slug, 'role' => $role->id])->assertForbidden();

    expect($role->refresh()->name)->toBe(ManagementRoles::SUPER_ADMIN);
});

test('domain role writes refuse protected roles', function (string $action) {
    $role = Realm::master()->roles()->where('name', ManagementRoles::SUPER_ADMIN)->sole();

    expect(fn () => $action === 'update'
        ? app(UpdateRole::class)->handle($role, 'changed', null, [])
        : app(DeleteRole::class)->handle($role))
        ->toThrow(LogicException::class)
        ->and($role->refresh()->name)->toBe(ManagementRoles::SUPER_ADMIN)
        ->and($role->scopes()->exists())->toBeTrue();
})->with(['update', 'delete']);

test('domain role writes reject foreign scopes without changing the role', function (string $action) {
    $resource = Resource::factory()->for($this->other)->create();
    $scope = ResourceScope::factory()->for($resource)->create();
    $role = Role::factory()->for($this->realm)->create(['name' => 'unchanged']);

    expect(fn () => $action === 'update'
        ? app(UpdateRole::class)->handle($role, 'changed', null, [$scope->id])
        : app(CreateRole::class)->handle($this->realm, 'changed', null, [$scope->id]))
        ->toThrow(ValidationException::class)
        ->and($role->refresh()->name)->toBe('unchanged')
        ->and($this->realm->roles()->count())->toBe(1);
})->with(['update', 'create']);

test('replacing a role scope with the same value from another resource records the privilege change', function () {
    $firstResource = Resource::factory()->for($this->realm)->create(['identifier' => 'documents']);
    $secondResource = Resource::factory()->for($this->realm)->create(['identifier' => 'billing']);
    $firstScope = ResourceScope::factory()->for($firstResource)->create(['value' => 'read']);
    $secondScope = ResourceScope::factory()->for($secondResource)->create(['value' => 'read']);
    $role = Role::factory()->for($this->realm)->create();
    $role->scopes()->attach($firstScope);

    app(UpdateRole::class)->handle($role, $role->name, $role->description, [$secondScope->id]);

    expect($role->scopes()->pluck('resource_scopes.id')->all())->toBe([$secondScope->id]);

    $event = AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::RoleUpdated->type())->sole();

    expect(data_get($event->context, 'changes.scopes'))->toBe([
        'old' => [['resource' => 'documents', 'scope' => 'read']],
        'new' => [['resource' => 'billing', 'scope' => 'read']],
    ]);

    app(UpdateRole::class)->handle($role, $role->name, $role->description, [$secondScope->id]);

    expect(AdminEvent::forRealm($this->realm)->where('type', RoleAdminEvent::RoleUpdated->type())->count())->toBe(1);
});
