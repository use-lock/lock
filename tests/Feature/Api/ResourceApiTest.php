<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Audit\Models\AdminEvent;
use App\Realms\Models\Realm;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;

use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\realmUrl;

it('requires authentication and the resource write scope', function () {
    $realm = Realm::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources");

    $this->getJson($url)->assertUnauthorized();
    $this->withToken(managementApiToken($this, ManagementScope::ResourcesRead))
        ->postJson($url, ['identifier' => 'orders', 'name' => 'Orders'])
        ->assertForbidden();

    expect($realm->realmResources()->count())->toBe(0);
});

it('lists and filters only resources of the addressed realm in the requested order', function () {
    $realm = Realm::factory()->create();
    Resource::factory()->for($realm)->create(['identifier' => 'orders', 'name' => 'Orders']);
    $billing = Resource::factory()->for($realm)->create(['identifier' => 'billing', 'name' => 'Billing']);
    Resource::factory()->create(['identifier' => 'billing', 'name' => 'Another realm']);
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources");
    $token = managementApiToken($this, ManagementScope::ResourcesRead);

    $this->withToken($token)->getJson($url.'?sort=-name&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Orders');
    auth()->forgetGuards();
    $this->withToken($token)->getJson($url.'?filter[identifier]=billing')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $billing->id)
        ->assertJsonPath('data.0.realm', $realm->slug);
});

it('does not read or mutate a resource through another realm', function (string $method) {
    $resource = Resource::factory()->create(['name' => 'Original']);
    $realm = Realm::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources/{$resource->id}");

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesRead, ManagementScope::ResourcesWrite))
        ->json($method, $url, ['name' => 'Changed'])->assertNotFound();

    expect($resource->refresh()->name)->toBe('Original');
})->with(['GET', 'PATCH', 'DELETE']);

it('creates and shows a resource with an audit record', function () {
    $realm = Realm::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources");
    $token = managementApiToken($this, ManagementScope::ResourcesRead, ManagementScope::ResourcesWrite);
    $response = $this->withToken($token)->postJson($url, ['identifier' => 'https://orders.test/api', 'name' => 'Orders'])
        ->assertCreated()->assertJsonPath('data.identifier', 'https://orders.test/api')->assertJsonPath('data.realm', $realm->slug);
    $resource = $realm->realmResources()->whereKey($response->json('data.id'))->sole();

    expect($resource->name)->toBe('Orders')
        ->and(AdminEvent::forRealm($realm)->where('type', ResourceAdminEvent::ResourceCreated->type())->sole()->subject_id)->toBe($resource->id);
    auth()->forgetGuards();
    $this->withToken($token)->getJson($url.'/'.$resource->id)->assertOk()->assertJsonPath('data.name', 'Orders');
});

it('rejects invalid resource payloads without writing', function (array $payload, string $field) {
    $realm = Realm::factory()->create();

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($realm->realmResources()->count())->toBe(0);
})->with([
    'missing name' => [['identifier' => 'orders'], 'name'],
    'invalid path' => [['identifier' => '/orders', 'name' => 'Orders'], 'identifier'],
    'fragment' => [['identifier' => 'https://orders.test/api#fragment', 'name' => 'Orders'], 'identifier'],
    'no host' => [['identifier' => 'urn:orders', 'name' => 'Orders'], 'identifier'],
    'unknown attribute' => [['identifier' => 'orders', 'name' => 'Orders', 'realm_id' => 'other'], 'realm_id'],
    'long name' => [['identifier' => 'orders', 'name' => str_repeat('x', 256)], 'name'],
]);

it('enforces identifier uniqueness within the realm on create and update', function () {
    $realm = Realm::factory()->create();
    Resource::factory()->for($realm)->create(['identifier' => 'orders']);
    $resource = Resource::factory()->for($realm)->create(['identifier' => 'billing']);
    $token = managementApiToken($this, ManagementScope::ResourcesWrite);
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources");

    $this->withToken($token)->postJson($url, ['identifier' => 'orders', 'name' => 'Duplicate'])
        ->assertUnprocessable()->assertJsonValidationErrors('identifier');
    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url.'/'.$resource->id, ['identifier' => 'orders'])
        ->assertUnprocessable()->assertJsonValidationErrors('identifier');

    expect($resource->refresh()->identifier)->toBe('billing')
        ->and($realm->realmResources()->count())->toBe(2);
});

it('keeps omitted update values and does not audit a no-op', function () {
    $resource = Resource::factory()->create(['identifier' => 'orders', 'name' => 'Orders']);
    $token = managementApiToken($this, ManagementScope::ResourcesWrite);
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}");

    $this->withToken($token)->patchJson($url, ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.identifier', 'orders');
    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, [])->assertOk();

    expect($resource->refresh()->name)->toBe('Renamed')
        ->and(AdminEvent::forRealm($resource->realm)->where('type', ResourceAdminEvent::ResourceUpdated->type())->count())->toBe(1);
});

it('deletes resources and their scopes and records the deletion', function () {
    $resource = Resource::factory()->create();
    $scope = ResourceScope::factory()->for($resource)->create();

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->deleteJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"))
        ->assertNoContent();

    $this->assertModelMissing($resource);
    $this->assertModelMissing($scope);
    expect(AdminEvent::forRealm($resource->realm)->where('type', ResourceAdminEvent::ResourceDeleted->type())->sole()->context)
        ->toMatchArray(['scopes-deleted' => 1]);
});

it('forbids management resource mutations with a normal API denial', function (string $method) {
    $token = managementApiToken($this, ManagementScope::ResourcesWrite);
    $resource = Realm::master()->realmResources()->where('identifier', ManagementApi::RESOURCE)->sole();

    $this->withToken($token)->json($method,
        realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), ['name' => 'Changed'])
        ->assertForbidden();

    expect($resource->refresh()->name)->toBe(ManagementApi::NAME);
})->with(['PATCH', 'DELETE']);

it('allows the same identifier in different realms', function () {
    Resource::factory()->create(['identifier' => 'orders']);
    $realm = Realm::factory()->create();

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/resources"), ['identifier' => 'orders', 'name' => 'Orders'])
        ->assertCreated();

    expect($realm->realmResources()->sole()->identifier)->toBe('orders');
});

it('validates patch values before changing a resource', function (array $payload, string $field) {
    $resource = Resource::factory()->create(['identifier' => 'orders', 'name' => 'Orders']);

    $this->withToken(managementApiToken($this, ManagementScope::ResourcesWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$resource->realm->slug}/resources/{$resource->id}"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($resource->refresh()->only(['identifier', 'name']))->toBe(['identifier' => 'orders', 'name' => 'Orders']);
})->with([
    'invalid identifier' => [['identifier' => '/invalid'], 'identifier'],
    'null name' => [['name' => null], 'name'],
    'unknown attribute' => [['realm_id' => 'other'], 'realm_id'],
]);
