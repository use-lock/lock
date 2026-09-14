<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;
use function Tests\Helpers\playgroundTokenRequest;
use function Tests\Helpers\provisionManagementApi;
use function Tests\Helpers\realmUrl;
use function Tests\Helpers\selfSsoProviderFake;

beforeEach(function () {
    provisionManagementApi();
});

it('mints a scoped token on execute that the API accepts', function () {
    selfSsoProviderFake();

    $request = playgroundTokenRequest(ManagementScope::RealmsRead);

    $token = $this->actingAs(globalAdmin())
        ->withHeader('X-Lattice-Ref', $request['ref'])
        ->postJson($request['endpoint'], $request['payload'])
        ->assertOk()
        ->json();

    expect($token['audience'])->toBe(app(ManagementApi::class)->audience())
        ->and($token['scopes'])->toBe([ManagementScope::RealmsRead->value])
        ->and($token['expiresIn'])->toBeLessThanOrEqual(300);

    auth()->forgetGuards();

    $this->withToken($token['accessToken'])
        ->getJson(realmUrl(Realm::master(), '/api/v1/realms'))
        ->assertOk()
        ->assertJsonPath('data.0.slug', Realm::master()->slug);
});

it('refuses to mint a token for an admin who cannot write realms', function () {
    $request = playgroundTokenRequest(ManagementScope::RealmsWrite);

    $this->actingAs(globalAdminWith(ManagementScope::RealmsRead))
        ->withHeader('X-Lattice-Ref', $request['ref'])
        ->postJson($request['endpoint'], $request['payload'])
        ->assertForbidden();
});

it('closes the page to an admin without realms:read', function () {
    $this->actingAs(globalAdminWith(ManagementScope::AdminEventsRead))->get(route('admin.api'))->assertForbidden();
});
