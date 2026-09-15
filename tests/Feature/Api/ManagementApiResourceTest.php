<?php

declare(strict_types=1);

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Ui\Actions\CreateResourceScopeAction;
use App\Resources\Ui\Actions\DeleteResourceAction;
use App\Resources\Ui\Actions\DeleteResourceScopeAction;
use App\Resources\Ui\Actions\UpdateResourceAction;
use App\Resources\Ui\Actions\UpdateResourceScopeAction;
use App\Roles\Models\Role;
use Illuminate\Support\Facades\Artisan;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\provisionManagementApi;

beforeEach(function () {
    $this->resource = provisionManagementApi();
    $this->scope = $this->resource->scopes()->where('value', ManagementScope::ResourcesRead->value)->sole();
    $this->context = ['realm' => Realm::master()->slug, 'resource' => $this->resource->id];
});

it('reconciles the resource and its scopes, and reports a repeat run as unchanged', function () {
    expect($this->resource->name)->toBe(ManagementApi::NAME)
        ->and($this->resource->scopes->pluck('value')->all())->toEqualCanonicalizing(ApiResource::Management->values())
        ->and($this->scope->description)->toBe(ManagementScope::ResourcesRead->description())
        ->and(app(ManagementApi::class)->reconcile(Realm::master())->value)->toBe('unchanged');
});

it('restores a scope an administrator changed in the database', function () {
    $this->scope->forceFill(['description' => 'Tampered'])->save();
    $this->resource->scopes()->create(['value' => 'realms:everything']);

    expect(app(ManagementApi::class)->reconcile(Realm::master())->value)->toBe('updated')
        ->and($this->scope->refresh()->description)->toBe(ManagementScope::ResourcesRead->description())
        ->and($this->resource->refresh()->scopes->pluck('value')->all())->toEqualCanonicalizing(ApiResource::Management->values());
});

it('offers a console admin no way to rename, delete or extend the resource', function () {
    $admin = globalAdmin();

    $this->actingAs($admin)->callDeniedAction(UpdateResourceAction::class, [
        'identifier' => 'taken-over',
        'name' => 'Mine now',
    ], $this->context);

    $this->actingAs($admin)->callDeniedAction(DeleteResourceAction::class, [], $this->context);

    $this->actingAs($admin)->callDeniedAction(CreateResourceScopeAction::class, [
        'value' => 'realms:everything',
        'description' => '',
    ], $this->context);

    expect($this->resource->refresh()->identifier)->toBe(ManagementApi::RESOURCE)
        ->and($this->resource->scopes()->count())->toBe(count(ApiResource::Management->values()));
});

it('offers a console admin no way to change or delete one of its scopes', function () {
    $admin = globalAdmin();
    $context = [...$this->context, 'resourceScope' => $this->scope->id];

    $this->actingAs($admin)->callDeniedAction(UpdateResourceScopeAction::class, [
        'value' => 'realms:everything',
        'description' => 'Mine now',
    ], $context);

    $this->actingAs($admin)->callDeniedAction(DeleteResourceScopeAction::class, [], $context);

    expect($this->scope->refresh()->value)->toBe(ManagementScope::ResourcesRead->value);
});

it('leaves another realm’s resource of the same identifier editable', function () {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $resource = Resource::factory()->for($realm)->create(['identifier' => ManagementApi::RESOURCE]);

    $this->actingAs(globalAdmin())->callAction(UpdateResourceAction::class, [
        'identifier' => ManagementApi::RESOURCE,
        'name' => 'Acme API',
    ], ['realm' => 'acme', 'resource' => $resource->id])->assertOk();

    expect($resource->refresh()->name)->toBe('Acme API');
});

it('is reconciled by app:bootstrap', function () {
    $this->resource->delete();

    Artisan::call('app:bootstrap');

    expect(Realm::master()->realmResources()->where('identifier', ManagementApi::RESOURCE)->exists())->toBeTrue();
});

it('keeps the two protected resources and their scopes disjoint', function () {
    $admin = Realm::master()->realmResources()->where('identifier', 'admin-api')->sole();

    expect($admin->name)->toBe('Admin API')
        ->and($admin->scopes->pluck('value')->all())->toEqualCanonicalizing(['realms:read', 'realms:write'])
        ->and($this->resource->scopes->pluck('value')->all())->not->toContain('realms:read', 'realms:write')
        ->toContain('social-providers:read', 'social-providers:write')
        ->and(app(ManagementApi::class)->owns($admin))->toBeTrue()
        ->and(app(ManagementApi::class)->ownsScope($admin->scopes()->where('value', 'realms:read')->with('resource.realm')->sole()))->toBeTrue();
});

it('moves legacy realm scopes without changing their IDs or losing custom role grants', function (string $access) {
    $realm = Realm::master();
    $admin = $realm->realmResources()->where('identifier', 'admin-api')->sole();
    $scope = $admin->scopes()->where('value', 'realms:'.$access)->sole();
    $scope->resource()->associate($this->resource);
    $scope->save();
    $admin->delete();
    $this->resource->scopes()->whereIn('value', ['social-providers:read', 'social-providers:write'])->delete();
    $role = Role::factory()->for($realm)->create();
    $role->scopes()->attach($scope);
    $user = User::factory()->for($realm)->create();
    $user->roles()->attach($role);

    $migration = require database_path('data-migrations/2026_09_15_122235_split_admin_and_management_api_resources.php');
    $migration->up();

    app(ManagementApi::class)->reconcile($realm);

    expect($scope->refresh()->resource->identifier)->toBe('admin-api')
        ->and($role->refresh()->scopes->modelKeys())->toContain($scope->id)
        ->and($user->managementScopes()->all())->toEqualCanonicalizing(['realms:'.$access, 'social-providers:'.$access]);

    $social = $this->resource->scopes()->where('value', 'social-providers:'.$access)->sole();
    $role->scopes()->detach($social);

    expect(app(ManagementApi::class)->reconcile($realm)->value)->toBe('unchanged')
        ->and($user->refresh()->managementScopes()->all())->toBe(['realms:'.$access]);
})->with(['read', 'write']);

it('rejects scopes from the wrong protected resource or realm', function () {
    $realm = Realm::master();
    $other = Realm::factory()->create();
    $foreign = Resource::factory()->for($other)->create(['identifier' => 'admin-api']);
    $role = Role::factory()->for($realm)->create();
    $role->scopes()->attach([
        $this->resource->scopes()->create(['value' => 'realms:write'])->id,
        $foreign->scopes()->create(['value' => 'realms:read'])->id,
    ]);
    $user = User::factory()->for($realm)->create();
    $user->roles()->attach($role);

    expect($user->managementScopes()->all())->toBeEmpty();

    app(ManagementApi::class)->reconcile($realm);

    expect($user->refresh()->managementScopes()->all())->toBeEmpty();
});
