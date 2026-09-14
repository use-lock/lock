<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Resources\Ui\Actions\CreateResourceAction;
use App\Resources\Ui\Actions\CreateResourceScopeAction;
use App\Resources\Ui\Actions\DeleteResourceAction;
use App\Resources\Ui\Actions\DeleteResourceScopeAction;
use App\Resources\Ui\Actions\UpdateResourceAction;
use App\Resources\Ui\Actions\UpdateResourceScopeAction;
use App\Resources\Ui\Tables\ResourceScopesTable;
use App\Resources\Ui\Tables\ResourcesTable;
use Illuminate\Database\Eloquent\Builder;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->other = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex']);
});

/**
 * The master realm carries the management API's own resource and scopes, so a
 * count has to stay inside the realm under test.
 *
 * @return Builder<ResourceScope>
 */
function realmScopes(Realm $realm): Builder
{
    return ResourceScope::query()->whereHas('resource', fn (Builder $query) => $query->where('realm_id', $realm->id));
}

$resourceViewer = fn (): User => globalAdminWith(ManagementScope::ResourcesRead);

test('a resources-manage admin adds a resource and it is recorded', function () {
    $this->actingAs(globalAdmin())->callAction(CreateResourceAction::class, [
        'identifier' => '  api  ',
        'name' => 'Orders API',
    ], ['realm' => 'acme'])->assertOk();

    $resource = $this->realm->realmResources()->sole();

    expect($resource->identifier)->toBe('api')
        ->and($resource->name)->toBe('Orders API')
        ->and($resource->realm_id)->toBe($this->realm->id);

    $event = AdminEvent::forRealm($this->realm)->where('type', ResourceAdminEvent::ResourceCreated->type())->sole();

    expect($event->subject_id)->toBe($resource->id)
        ->and(data_get($event->context, 'realm'))->toBe('acme')
        ->and(data_get($event->context, 'identifier'))->toBe('api');
});

test('an identifier is unique per realm but free in another', function () {
    Resource::factory()->for($this->realm)->create(['identifier' => 'api']);

    $this->actingAs(globalAdmin());
    $this->callAction(CreateResourceAction::class, ['identifier' => 'api', 'name' => 'Duplicate'], ['realm' => 'acme'])->assertInvalid(['identifier']);
    $this->callAction(CreateResourceAction::class, ['identifier' => 'api', 'name' => 'Elsewhere'], ['realm' => 'globex'])->assertOk();

    expect(Resource::query()->where('identifier', 'api')->whereIn('realm_id', [$this->realm->id, $this->other->id])->count())->toBe(2);
});

test('an identifier is rejected when it is neither a path nor an absolute uri', function (string $identifier) {
    $this->actingAs(globalAdmin())
        ->callAction(CreateResourceAction::class, ['identifier' => $identifier, 'name' => 'Broken'], ['realm' => 'acme'])
        ->assertInvalid(['identifier']);

    expect($this->realm->realmResources()->exists())->toBeFalse();
})->with([
    'whitespace' => 'orders api',
    'leading slash' => '/api',
    'uri with a fragment' => 'https://billing.test/api#frag',
    'scheme without a host' => 'urn:orders',
]);

test('an absolute uri is accepted as an identifier', function () {
    $this->actingAs(globalAdmin())
        ->callAction(CreateResourceAction::class, ['identifier' => 'https://billing.test', 'name' => 'Billing'], ['realm' => 'acme'])
        ->assertOk();

    expect($this->realm->realmResources()->sole()->isAbsolute())->toBeTrue();
});

test('renaming and deleting a resource works from the console', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api', 'name' => 'Orders API']);
    $context = ['realm' => 'acme', 'resource' => $resource->id];

    $this->actingAs(globalAdmin());
    $this->callAction(UpdateResourceAction::class, ['identifier' => 'orders', 'name' => 'Orders'], $context)->assertOk();

    expect($resource->refresh()->identifier)->toBe('orders');

    $this->callAction(DeleteResourceAction::class, [], $context)->assertOk();

    expect($this->realm->realmResources()->exists())->toBeFalse();
});

test('two resources of one realm carry the same scope value, one resource does not', function () {
    $orders = Resource::factory()->for($this->realm)->create(['identifier' => 'orders']);
    $reports = Resource::factory()->for($this->realm)->create(['identifier' => 'reports']);

    $this->actingAs(globalAdmin());
    $this->callAction(CreateResourceScopeAction::class, ['value' => 'read', 'description' => '  Read orders  '], ['realm' => 'acme', 'resource' => $orders->id])->assertOk();
    $this->callAction(CreateResourceScopeAction::class, ['value' => 'read', 'description' => ''], ['realm' => 'acme', 'resource' => $reports->id])->assertOk();
    $this->callAction(CreateResourceScopeAction::class, ['value' => 'read'], ['realm' => 'acme', 'resource' => $orders->id])->assertInvalid(['value']);

    expect(realmScopes($this->realm)->where('value', 'read')->count())->toBe(2)
        ->and($orders->scopes()->sole()->description)->toBe('Read orders')
        ->and($reports->scopes()->sole()->description)->toBeNull();
});

test('a scope value is rejected when it carries whitespace', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api']);

    $this->actingAs(globalAdmin())
        ->callAction(CreateResourceScopeAction::class, ['value' => 'orders read'], ['realm' => 'acme', 'resource' => $resource->id])
        ->assertInvalid(['value']);

    expect(realmScopes($this->realm)->exists())->toBeFalse();
});

test('a scope is edited and deleted from its resource page', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api']);
    $scope = ResourceScope::factory()->for($resource)->create(['value' => 'read', 'description' => null]);
    $context = ['realm' => 'acme', 'resource' => $resource->id, 'resourceScope' => $scope->id];

    $this->actingAs(globalAdmin());
    $this->callAction(UpdateResourceScopeAction::class, ['value' => 'orders.read', 'description' => 'Read orders'], $context)->assertOk();

    expect($scope->refresh()->value)->toBe('orders.read')
        ->and($scope->description)->toBe('Read orders');

    $this->callAction(DeleteResourceScopeAction::class, [], $context)->assertOk();

    expect(realmScopes($this->realm)->exists())->toBeFalse();
});

test('the resources table lists only the selected realm with its scope counts', function () {
    $orders = Resource::factory()->for($this->realm)->create(['identifier' => 'orders', 'name' => 'Orders']);
    Resource::factory()->for($this->realm)->create(['identifier' => 'api', 'name' => 'API']);
    Resource::factory()->for($this->other)->create(['identifier' => 'elsewhere', 'name' => 'Elsewhere']);
    ResourceScope::factory()->for($orders)->create(['value' => 'read']);

    $rows = $this->actingAs(globalAdmin())
        ->loadTable(ResourcesTable::class, context: ['realm' => 'acme'])
        ->assertOk()
        ->json('data');

    $rows = collect(is_array($rows) ? $rows : []);

    expect($rows->pluck('identifier')->all())->toBe(['api', 'orders'])
        ->and($rows->firstWhere('identifier', 'orders')['scopes_count'])->toBe(1);
});

test('the scopes table is scoped to its resource and reachable only through its realm', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api']);
    ResourceScope::factory()->for($resource)->create(['value' => 'read']);
    ResourceScope::factory()->for(Resource::factory()->for($this->realm)->create(['identifier' => 'other']))->create(['value' => 'write']);

    $this->actingAs(globalAdmin());

    $rows = $this->loadTable(ResourceScopesTable::class, context: ['realm' => 'acme', 'resource' => $resource->id])
        ->assertOk()
        ->json('data');

    expect(collect(is_array($rows) ? $rows : [])->pluck('value')->all())->toBe(['read']);

    $this->loadDeniedTable(ResourceScopesTable::class, context: ['realm' => 'globex', 'resource' => $resource->id])->assertNotFound();
});

test('a resource is only reachable through its own realm and a scope only through its own resource', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api', 'name' => 'Orders API']);
    $scope = ResourceScope::factory()->for($resource)->create(['value' => 'read']);
    $foreign = Resource::factory()->for($this->realm)->create(['identifier' => 'other']);

    $this->actingAs(globalAdmin());
    $this->callDeniedAction(UpdateResourceAction::class, ['identifier' => 'renamed', 'name' => 'Renamed'], ['realm' => 'globex', 'resource' => $resource->id])->assertForbidden();
    $this->callDeniedAction(DeleteResourceAction::class, [], ['realm' => 'globex', 'resource' => $resource->id])->assertForbidden();
    $this->callDeniedAction(DeleteResourceScopeAction::class, [], ['realm' => 'acme', 'resource' => $foreign->id, 'resourceScope' => $scope->id])->assertForbidden();

    expect($resource->refresh()->identifier)->toBe('api')
        ->and(ResourceScope::query()->whereKey($scope->id)->exists())->toBeTrue();
});

test('a view-only admin reads the console but cannot change anything', function () use ($resourceViewer) {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api', 'name' => 'Orders API']);
    $scope = ResourceScope::factory()->for($resource)->create(['value' => 'read']);
    $context = ['realm' => 'acme', 'resource' => $resource->id, 'resourceScope' => $scope->id];

    $this->actingAs($resourceViewer());
    $this->get('/admin/realms/acme/resources')->assertOk();
    $this->get("/admin/realms/acme/resources/{$resource->id}")->assertOk();
    $this->loadTable(ResourcesTable::class, context: ['realm' => 'acme'])->assertOk();

    $this->callDeniedAction(CreateResourceAction::class, ['identifier' => 'new', 'name' => 'New'], ['realm' => 'acme'])->assertForbidden();
    $this->callDeniedAction(UpdateResourceAction::class, ['identifier' => 'renamed', 'name' => 'Renamed'], $context)->assertForbidden();
    $this->callDeniedAction(DeleteResourceAction::class, [], $context)->assertForbidden();
    $this->callDeniedAction(CreateResourceScopeAction::class, ['value' => 'write'], $context)->assertForbidden();
    $this->callDeniedAction(UpdateResourceScopeAction::class, ['value' => 'write'], $context)->assertForbidden();
    $this->callDeniedAction(DeleteResourceScopeAction::class, [], $context)->assertForbidden();

    expect($resource->refresh()->identifier)->toBe('api')
        ->and($this->realm->realmResources()->count())->toBe(1)
        ->and($scope->refresh()->value)->toBe('read');
});

test('the resources console is closed without a resources scope', function () {
    $resource = Resource::factory()->for($this->realm)->create(['identifier' => 'api']);

    $this->actingAs(globalAdminWith(ManagementScope::AdminEventsRead));
    $this->get('/admin/realms/acme/resources')->assertForbidden();
    $this->get("/admin/realms/acme/resources/{$resource->id}")->assertForbidden();
    $this->loadDeniedTable(ResourcesTable::class, context: ['realm' => 'acme'])->assertForbidden();
    $this->callDeniedAction(CreateResourceAction::class, ['identifier' => 'new', 'name' => 'New'], ['realm' => 'acme'])->assertForbidden();

    expect($this->realm->realmResources()->count())->toBe(1);
});
