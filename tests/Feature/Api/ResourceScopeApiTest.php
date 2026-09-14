<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Models\Role;

use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\realmUrl;

it('creates a resource with scopes and returns them on reads', function () {
    $realm = Realm::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources");
    $this->withToken(managementApiToken($this, ManagementScope::ResourcesRead, ManagementScope::ResourcesWrite));

    $response = $this->postJson($url, [
        'identifier' => 'orders', 'name' => 'Orders',
        'scopes' => [['value' => 'read', 'description' => 'Read orders'], ['value' => 'write']],
    ])->assertCreated()->assertJsonCount(2, 'data.scopes')->assertJsonMissingPath('data.scopes.0.id')->assertJsonMissingPath('data.scopes.0.resource_id');
    $resource = $realm->realmResources()->whereKey($response->json('data.id'))->sole();
    $scope = $resource->scopes()->where('value', 'read')->sole();
    expect($scope->description)->toBe('Read orders');
    auth()->forgetGuards();

    $this->getJson($url.'/'.$resource->id)->assertOk()->assertJsonFragment(['value' => 'read', 'description' => 'Read orders']);
    auth()->forgetGuards();
    $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.0.scopes');
});

it('adds updates and deletes scopes together while retaining unmentioned scopes', function () {
    $resource = Resource::factory()->create();
    $updated = ResourceScope::factory()->for($resource)->create(['value' => 'read', 'description' => 'Read orders']);
    $deleted = ResourceScope::factory()->for($resource)->create(['value' => 'write']);
    $retained = ResourceScope::factory()->for($resource)->create(['value' => 'manage']);
    $role = Role::factory()->for($resource->realm)->create();
    $role->scopes()->attach([$updated->id, $deleted->id]);
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}");

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))->patchJson($url, [
        'name' => 'Changed',
        'scopes' => [
            ['value' => 'create', 'description' => 'Create orders'],
            ['value' => 'read', 'description' => null],
            ['value' => 'write', 'delete' => true],
        ],
    ])->assertOk()->assertJsonPath('data.name', 'Changed')->assertJsonCount(3, 'data.scopes');

    expect($updated->refresh()->value)->toBe('read')->and($updated->description)->toBeNull()
        ->and($resource->scopes()->where('value', 'create')->sole()->description)->toBe('Create orders')
        ->and($role->scopes()->pluck('resource_scopes.id')->all())->toBe([$updated->id]);
    $this->assertModelMissing($deleted);
    $this->assertModelExists($retained);
});

it('keeps scopes when the collection is omitted or empty', function (array $payload) {
    $scope = ResourceScope::factory()->create();
    $resource = $scope->resource;

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), $payload)
        ->assertOk()->assertJsonPath('data.scopes.0.value', $scope->value);

    $this->assertModelExists($scope);
})->with([[['name' => 'Changed']], [['scopes' => []]]]);

it('does not borrow scope values from another resource when updating or deleting', function (bool $delete) {
    $resource = Resource::factory()->create(['name' => 'Original']);
    $foreign = ResourceScope::factory()->create(['value' => 'read', 'description' => 'Foreign']);
    $updated = ResourceScope::factory()->for($resource)->create(['value' => 'update', 'description' => 'Original']);
    $deleted = ResourceScope::factory()->for($resource)->create(['value' => 'delete']);
    $role = Role::factory()->for($resource->realm)->create();
    $role->scopes()->attach($deleted);
    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite));
    $events = AdminEvent::query()->count();

    $response = $this->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), [
        'name' => 'Changed',
        'scopes' => [
            ['value' => 'update', 'description' => 'Changed'],
            ['value' => 'delete', 'delete' => true],
            ['value' => 'new'],
            ['value' => 'read', 'delete' => $delete, 'description' => 'Local'],
        ],
    ]);

    if ($delete) {
        $response->assertUnprocessable()->assertJsonValidationErrors('scopes.3.value');
        expect($resource->refresh()->name)->toBe('Original')->and($resource->scopes()->count())->toBe(2)
            ->and($updated->refresh()->description)->toBe('Original')
            ->and($role->scopes()->pluck('resource_scopes.id')->all())->toBe([$deleted->id])
            ->and(AdminEvent::query()->count())->toBe($events);
    } else {
        $response->assertOk();
        expect($resource->scopes()->where('value', 'read')->sole()->description)->toBe('Local');
    }

    expect($foreign->refresh()->description)->toBe('Foreign');
})->with([true, false]);

it('rejects malformed nested scope commands', function (array $payload, string $field) {
    $resource = Resource::factory()->create();

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($resource->scopes()->count())->toBe(0);
})->with([
    'missing value' => [['scopes' => [[]]], 'scopes.0.value'],
    'delete without value' => [['scopes' => [['delete' => true]]], 'scopes.0.value'],
    'id is not accepted' => [['scopes' => [['value' => 'read', 'id' => 'a615c979-4a08-4a12-a86d-43f06054f847']]], 'scopes.0.id'],
    'space in value' => [['scopes' => [['value' => 'orders read']]], 'scopes.0.value'],
    'long value' => [['scopes' => [['value' => str_repeat('a', 256)]]], 'scopes.0.value'],
    'long description' => [['scopes' => [['value' => 'read', 'description' => str_repeat('a', 256)]]], 'scopes.0.description'],
    'unknown property' => [['scopes' => [['value' => 'read', 'resource_id' => 'other']]], 'scopes.0.resource_id'],
    'invalid delete' => [['scopes' => [['value' => 'read', 'delete' => 'yes']]], 'scopes.0.delete'],
    'null collection' => [['scopes' => null], 'scopes'],
]);

it('rejects repeated scope commands and leaves the resource unchanged', function () {
    $resource = Resource::factory()->create(['name' => 'Original']);
    $scope = ResourceScope::factory()->for($resource)->create(['value' => 'read', 'description' => 'Original']);

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), [
            'name' => 'Changed', 'scopes' => [['value' => 'read', 'description' => 'Changed'], ['value' => 'read', 'delete' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors('scopes.1.value');

    expect($resource->refresh()->name)->toBe('Original')->and($scope->refresh()->description)->toBe('Original');
});

it('rolls back resource creation when nested scope values conflict', function () {
    $realm = Realm::factory()->create();
    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite));
    $events = AdminEvent::query()->count();

    $this->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources"), [
        'identifier' => 'orders', 'name' => 'Orders', 'scopes' => [['value' => 'read'], ['value' => 'read']],
    ])->assertUnprocessable()->assertJsonValidationErrors('scopes.1.value');

    expect($realm->realmResources()->count())->toBe(0)->and(AdminEvent::query()->count())->toBe($events);
});

it('allows scope values in different resources and unchanged values on update', function () {
    ResourceScope::factory()->create(['value' => 'read']);
    $scope = ResourceScope::factory()->create(['value' => 'read']);
    $resource = $scope->resource;

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), [
            'scopes' => [['value' => 'read', 'description' => 'Changed', 'delete' => false]],
        ])->assertOk();

    expect($scope->refresh()->description)->toBe('Changed');
});

it('forbids changes to nested management scopes', function (array $command) {
    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite));
    $resource = Realm::master()->realmResources()->where('identifier', 'api')->sole();
    $scope = $resource->scopes()->where('value', ManagementScope::ResourcesRead->value)->sole();

    $this->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), [
        'scopes' => [$command],
    ])->assertForbidden();

    expect($scope->refresh()->value)->toBe(ManagementScope::ResourcesRead->value)
        ->and($resource->scopes()->whereIn('value', ['new', 'changed'])->exists())->toBeFalse();
})->with([
    'create' => [['value' => 'new']],
    'update' => [['value' => ManagementScope::ResourcesRead->value, 'description' => 'Changed']],
    'delete' => [['value' => ManagementScope::ResourcesRead->value, 'delete' => true]],
]);

it('keeps an existing description when a scope entry omits it', function () {
    $scope = ResourceScope::factory()->create(['value' => 'read', 'description' => 'Read orders']);
    $resource = $scope->resource;

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), [
            'scopes' => [['value' => 'read'], ['value' => 'write']],
        ])->assertOk()->assertJsonPath('data.scopes.0.description', 'Read orders')
        ->assertJsonPath('data.scopes.1.description', null);

    expect($scope->refresh()->description)->toBe('Read orders')
        ->and($resource->scopes()->where('value', 'write')->sole()->description)->toBeNull();
});
