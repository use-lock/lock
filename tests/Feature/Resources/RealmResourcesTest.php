<?php

declare(strict_types=1);

use App\Audit\Models\AdminEvent;
use App\Realms\Models\Realm;
use App\Resources\Actions\CreateResource;
use App\Resources\Actions\CreateResourceScope;
use App\Resources\Actions\DeleteResource;
use App\Resources\Actions\DeleteResourceScope;
use App\Resources\Actions\UpdateResource;
use App\Resources\Actions\UpdateResourceScope;
use App\Resources\Data\CreateResourceData;
use App\Resources\Data\CreateResourceScopeData;
use App\Resources\Data\UpdateResourceData;
use App\Resources\Data\UpdateResourceScopeData;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
});

test('a realm serves its resources and their scopes to the oidc server', function () {
    $api = app(CreateResource::class)->handle($this->realm, CreateResourceData::from(['identifier' => 'api', 'name' => 'Orders API']));
    app(CreateResourceScope::class)->handle($api, CreateResourceScopeData::from(['value' => 'orders.read', 'description' => 'Read orders']));
    app(CreateResourceScope::class)->handle($api, CreateResourceScopeData::from(['value' => 'orders.write', 'description' => null]));

    $external = app(CreateResource::class)->handle($this->realm, CreateResourceData::from(['identifier' => 'https://billing.test', 'name' => 'Billing']));
    app(CreateResourceScope::class)->handle($external, CreateResourceScopeData::from(['value' => 'invoices.read', 'description' => null]));

    expect($this->realm->resources()->resources)->toBe([
        'api' => ['orders.read', 'orders.write'],
        'https://billing.test' => ['invoices.read'],
    ]);
});

test('creating, renaming and deleting a resource is recorded in the admin trail', function () {
    $resource = app(CreateResource::class)->handle($this->realm, CreateResourceData::from(['identifier' => 'api', 'name' => 'Orders API']));

    app(UpdateResource::class)->handle($resource, UpdateResourceData::from(['identifier' => 'orders', 'name' => 'Orders']));

    expect($resource->refresh()->identifier)->toBe('orders');

    $updated = AdminEvent::forRealm($this->realm)->where('type', ResourceAdminEvent::ResourceUpdated->type())->sole();

    expect(data_get($updated->context, 'changes'))->toBe([
        'identifier' => ['old' => 'api', 'new' => 'orders'],
        'name' => ['old' => 'Orders API', 'new' => 'Orders'],
    ]);

    app(CreateResourceScope::class)->handle($resource, CreateResourceScopeData::from(['value' => 'orders.read', 'description' => null]));
    app(DeleteResource::class)->handle($resource);

    expect(Resource::query()->whereKey($resource->id)->exists())->toBeFalse()
        ->and(ResourceScope::query()->where('resource_id', $resource->id)->exists())->toBeFalse();

    $deleted = AdminEvent::forRealm($this->realm)->where('type', ResourceAdminEvent::ResourceDeleted->type())->sole();

    expect(data_get($deleted->context, 'identifier'))->toBe('orders')
        ->and(data_get($deleted->context, 'scopes-deleted'))->toBe(1);
});

test('changing and dropping a scope is recorded against its resource', function () {
    $resource = app(CreateResource::class)->handle($this->realm, CreateResourceData::from(['identifier' => 'api', 'name' => 'Orders API']));
    $scope = app(CreateResourceScope::class)->handle($resource, CreateResourceScopeData::from(['value' => 'orders.read', 'description' => null]));

    app(UpdateResourceScope::class)->handle($scope, UpdateResourceScopeData::from(['value' => 'orders.read', 'description' => 'Read orders']));

    expect($scope->refresh()->label())->toBe('Read orders');

    $updated = AdminEvent::forRealm($this->realm)->where('type', ResourceAdminEvent::ScopeUpdated->type())->sole();

    expect(data_get($updated->context, 'resource'))->toBe('api')
        ->and(data_get($updated->context, 'changes'))->toBe([
            'description' => ['old' => null, 'new' => 'Read orders'],
        ]);

    app(DeleteResourceScope::class)->handle($scope);

    expect($this->realm->refresh()->resources()->resources)->toBe(['api' => []]);
});
