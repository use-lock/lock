<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Admin\Ui\Pages\ApiReferencePage;
use App\Admin\Ui\Remote\ApiReferenceTokens;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Shared\Audit\Audit;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Lattice\Core\Contracts\SignsComponentReferences;
use Tests\TestCase;

/**
 * The resource row and scopes `app:bootstrap` reconciles. Opt-in rather than
 * seeded for every test: only the API and the console's protection of it care.
 */
function provisionManagementApi(): Resource
{
    $realm = Realm::master();

    Audit::withoutRecording(fn () => app(ManagementApi::class)->reconcile($realm));

    return $realm->realmResources()->where('identifier', ManagementApi::RESOURCE)->sole();
}

/**
 * A machine bearer token for Lock's own management API: addressed to its
 * resource, carrying exactly the listed scopes and no user behind it.
 */
function managementApiToken(TestCase $test, ManagementScope ...$scopes): string
{
    provisionManagementApi();

    return $test->issueClientToken(
        $test->createOidcMachineClient(),
        array_column($scopes, 'value'),
        [app(ManagementApi::class)->audience(apiResourceForScopes(...$scopes))],
    );
}

/**
 * The playground's sealed reference for one scope set. Sealed against a
 * session-less request, which the signer treats as a wildcard: the JSON test
 * helpers forward no session cookie, so a ref lifted out of the rendered page
 * would carry a session hash the next request never has.
 *
 * @return array<string, mixed>
 */
function playgroundTokenRequest(ManagementScope ...$scopes): array
{
    $resource = apiResourceForScopes(...$scopes);
    $nodeId = $resource === ApiResource::Admin ? ApiReferencePage::REFERENCE_ID : ApiReferencePage::MANAGEMENT_REFERENCE_ID;
    $values = array_column($scopes, 'value');
    $context = [
        'audience' => app(ManagementApi::class)->audience($resource),
        'source' => ApiReferenceTokens::KEY,
        'scopes' => $values,
    ];

    app()->instance('request', Request::create((string) config('app.url')));

    return [
        'ref' => app(SignsComponentReferences::class)->seal('api-reference', $nodeId, $context),
        'endpoint' => route('lattice.remote-sources.token', ['source' => ApiReferenceTokens::KEY], absolute: false),
        'payload' => [
            'nodeId' => $nodeId,
            'nodeType' => 'api-reference',
            'audience' => $context['audience'],
            'scopes' => $values,
        ],
    ];
}

function apiResourceForScopes(ManagementScope ...$scopes): ApiResource
{
    $resource = ($scopes[0] ?? null)?->apiResource() ?? ApiResource::Management;
    foreach ($scopes as $scope) {
        if ($scope->apiResource() !== $resource) {
            throw new InvalidArgumentException('A token cannot combine Admin API and Management API scopes.');
        }
    }

    return $resource;
}
