<?php
declare(strict_types=1);

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;

use function Tests\Helpers\provisionManagementApi;
use function Tests\Helpers\realmUrl;

it('requires the audience of the API even when the token carries its scope', function (string $path, ManagementScope $scope, ApiResource $resource) {
    provisionManagementApi();
    $client = $this->createOidcMachineClient();
    $api = app(ManagementApi::class);
    $wrongResource = $resource === ApiResource::Admin ? ApiResource::Management : ApiResource::Admin;
    $path = str_replace('{realm}', Realm::master()->slug, $path);

    $this->withToken($this->issueClientToken($client, [$scope->value], [$api->audience($wrongResource)]))
        ->getJson(realmUrl(Realm::master(), $path))
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_token');

    auth()->forgetGuards();

    $this->withToken($this->issueClientToken($client, [$scope->value], [$api->audience($resource)]))
        ->getJson(realmUrl(Realm::master(), $path))
        ->assertOk();
})->with([
    'realm administration' => ['/api/v1/realms', ManagementScope::RealmsRead, ApiResource::Admin],
    'client management' => ['/api/v1/realms/{realm}/clients', ManagementScope::ClientsRead, ApiResource::Management],
    'social provider management' => ['/api/v1/realms/{realm}/social-providers', ManagementScope::SocialProvidersRead, ApiResource::Management],
]);
