<?php

declare(strict_types=1);

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use Lock\Server\Shared\Realms\IssuerResolver;
use Lock\Server\Shared\Scopes\Scope;
use Lock\Server\Shared\Scopes\ScopeRepository;

use function Tests\Helpers\clientCredentialsToken;
use function Tests\Helpers\jwtClaims;

beforeEach(function () {
    $this->realm = Realm::master();
    $this->issuer = app(IssuerResolver::class)->url();

    $orders = Resource::factory()->for($this->realm)->create(['identifier' => 'orders', 'name' => 'Orders']);
    $orders->scopes()->create(['value' => 'read', 'description' => 'Read orders']);
    $orders->scopes()->create(['value' => 'orders.write', 'description' => null]);

    $reports = Resource::factory()->for($this->realm)->create(['identifier' => 'reports', 'name' => 'Reports']);
    $reports->scopes()->create(['value' => 'read', 'description' => 'Read reports']);

    $this->orders = $this->issuer.'/orders';
    $this->reports = $this->issuer.'/reports';

    $this->client = $this->createOidcMachineClient();
    $this->client->forceFill(['allowed_exchange_audiences' => [$this->orders, $this->reports]])->save();
});

test('the catalog serves only the scopes of the requested resource', function () {
    $catalog = $this->realm->runAsCurrent(
        fn (): array => app(ScopeRepository::class)->all([$this->orders])
            ->mapWithKeys(fn (Scope $scope): array => [$scope->id => $scope->description])
            ->all(),
    );

    expect($catalog)->toHaveKeys(['read', 'orders.write'])
        ->and($catalog['read'])->toBe('Read orders')
        ->and($catalog['orders.write'])->toBe('orders.write');
});

test('two resources carry the same scope value without borrowing each other\'s meaning', function () {
    $forOrders = clientCredentialsToken($this, ['resource' => $this->orders, 'scope' => 'read'])->assertOk();
    $forReports = clientCredentialsToken($this, ['resource' => $this->reports, 'scope' => 'read'])->assertOk();

    expect($forOrders->json('scope'))->toBe('read')
        ->and($forReports->json('scope'))->toBe('read')
        ->and(jwtClaims($forOrders->json('access_token'))['aud'])->toBe($this->orders)
        ->and(jwtClaims($forReports->json('access_token'))['aud'])->toBe($this->reports);
});
