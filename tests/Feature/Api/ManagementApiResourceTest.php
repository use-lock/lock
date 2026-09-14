<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Ui\Actions\CreateResourceScopeAction;
use App\Resources\Ui\Actions\DeleteResourceAction;
use App\Resources\Ui\Actions\DeleteResourceScopeAction;
use App\Resources\Ui\Actions\UpdateResourceAction;
use App\Resources\Ui\Actions\UpdateResourceScopeAction;
use Illuminate\Support\Facades\Artisan;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\provisionManagementApi;

beforeEach(function () {
    $this->resource = provisionManagementApi();
    $this->scope = $this->resource->scopes()->where('value', ManagementScope::RealmsRead->value)->sole();
    $this->context = ['realm' => Realm::master()->slug, 'resource' => $this->resource->id];
});

it('reconciles the resource and its scopes, and reports a repeat run as unchanged', function () {
    expect($this->resource->name)->toBe(ManagementApi::NAME)
        ->and($this->resource->scopes->pluck('value')->all())->toEqualCanonicalizing(ManagementScope::values())
        ->and($this->scope->description)->toBe(ManagementScope::RealmsRead->description())
        ->and(app(ManagementApi::class)->reconcile(Realm::master())->value)->toBe('unchanged');
});

it('restores a scope an administrator changed in the database', function () {
    $this->scope->forceFill(['description' => 'Tampered'])->save();
    $this->resource->scopes()->create(['value' => 'realms:everything']);

    expect(app(ManagementApi::class)->reconcile(Realm::master())->value)->toBe('updated')
        ->and($this->scope->refresh()->description)->toBe(ManagementScope::RealmsRead->description())
        ->and($this->resource->refresh()->scopes->pluck('value')->all())->toEqualCanonicalizing(ManagementScope::values());
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
        ->and($this->resource->scopes()->count())->toBe(count(ManagementScope::values()));
});

it('offers a console admin no way to change or delete one of its scopes', function () {
    $admin = globalAdmin();
    $context = [...$this->context, 'resourceScope' => $this->scope->id];

    $this->actingAs($admin)->callDeniedAction(UpdateResourceScopeAction::class, [
        'value' => 'realms:everything',
        'description' => 'Mine now',
    ], $context);

    $this->actingAs($admin)->callDeniedAction(DeleteResourceScopeAction::class, [], $context);

    expect($this->scope->refresh()->value)->toBe(ManagementScope::RealmsRead->value);
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
