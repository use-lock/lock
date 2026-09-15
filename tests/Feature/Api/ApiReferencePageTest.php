<?php
declare(strict_types=1);

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Admin\Ui\Remote\ApiReferenceTokens;
use App\Realms\Models\Realm;
use Lattice\Core\Contracts\SignsComponentReferences;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;
use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\playgroundTokenRequest;
use function Tests\Helpers\provisionManagementApi;
use function Tests\Helpers\realmUrl;
use function Tests\Helpers\selfSsoProviderFake;

beforeEach(function () {
    provisionManagementApi();
});

it('mints a token for the selected API resource that its endpoint accepts', function (ManagementScope $scope, ApiResource $resource) {
    selfSsoProviderFake();

    $request = playgroundTokenRequest($scope);

    $token = $this->actingAs(globalAdmin())
        ->withHeader('X-Lattice-Ref', $request['ref'])
        ->postJson($request['endpoint'], $request['payload'])
        ->assertOk()
        ->json();

    expect($token['audience'])->toBe(app(ManagementApi::class)->audience($resource))
        ->and($token['scopes'])->toBe([$scope->value])
        ->and($token['expiresIn'])->toBeLessThanOrEqual(300);

    auth()->forgetGuards();

    $path = $resource === ApiResource::Admin
        ? '/api/v1/realms'
        : '/api/v1/realms/'.Realm::master()->slug.'/resources';

    $response = $this->withToken($token['accessToken'])
        ->getJson(realmUrl(Realm::master(), $path))
        ->assertOk();

    if ($resource === ApiResource::Admin) {
        $response->assertJsonPath('data.0.slug', Realm::master()->slug);
    } else {
        $response->assertJsonFragment(['identifier' => ApiResource::Management->value]);
    }
})->with([
    'Admin API' => [ManagementScope::RealmsRead, ApiResource::Admin],
    'Management API' => [ManagementScope::ResourcesRead, ApiResource::Management],
]);

it('rejects a signed playground request whose scopes do not belong to its audience', function (array $scopeValues, bool $wrongAudience) {
    $request = playgroundTokenRequest(ManagementScope::RealmsRead);
    $payload = $request['payload'];
    $payload['scopes'] = $scopeValues;
    if ($wrongAudience) {
        $payload['audience'] = app(ManagementApi::class)->audience(ApiResource::Management);
    }
    $reference = app(SignsComponentReferences::class)->seal('api-reference', $payload['nodeId'], [
        'audience' => $payload['audience'],
        'source' => ApiReferenceTokens::KEY,
        'scopes' => $scopeValues,
    ]);

    $this->actingAs(globalAdmin())
        ->withHeader('X-Lattice-Ref', $reference)
        ->postJson($request['endpoint'], $payload)
        ->assertForbidden();
})->with([
    'management scope under admin audience' => [[ManagementScope::ResourcesRead->value], false],
    'mixed scopes' => [[ManagementScope::RealmsRead->value, ManagementScope::ResourcesRead->value], false],
    'admin scope under management audience' => [[ManagementScope::RealmsRead->value], true],
    'unknown scope' => [['undeclared:read'], false],
    'no scopes' => [[], false],
]);

it('refuses to mint a token for an admin who cannot write realms', function () {
    $request = playgroundTokenRequest(ManagementScope::RealmsWrite);

    $this->actingAs(globalAdminWith(ManagementScope::RealmsRead))
        ->withHeader('X-Lattice-Ref', $request['ref'])
        ->postJson($request['endpoint'], $request['payload'])
        ->assertForbidden();
});

it('rejects mixed resource scopes in API token fixtures', function () {
    expect(fn () => managementApiToken($this, ManagementScope::RealmsRead, ManagementScope::ResourcesRead))
        ->toThrow(InvalidArgumentException::class);
});

it('closes the page to an admin without realms:read', function () {
    $this->actingAs(globalAdminWith(ManagementScope::AdminEventsRead))->get(route('admin.api'))->assertForbidden();
});
