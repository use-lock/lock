<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;
use Lock\Server\Shared\Realms\IssuerResolver;

use function Tests\Helpers\createRealm;
use function Tests\Helpers\jwtClaims;
use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\realmClient;
use function Tests\Helpers\realmRoute;
use function Tests\Helpers\realmUrl;

beforeEach(function () {
    $this->acme = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
    $this->client = realmClient($this->acme, 'Partner portal');
});

function clientsUrl(string $realm = 'acme', string $path = ''): string
{
    return realmUrl(Realm::master(), '/api/v1/realms/'.$realm.'/clients'.$path);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientPayload(array $overrides = []): array
{
    return [
        'name' => 'Checkout',
        'token_endpoint_auth_method' => TokenEndpointAuthMethod::ClientSecretBasic->value,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://checkout.test/callback'],
        ...$overrides,
    ];
}

it('refuses a request without a token', function () {
    $this->getJson(clientsUrl())->assertUnauthorized();
});

it('refuses a token that only grants the read scope for a write', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->postJson(clientsUrl(), clientPayload())
        ->assertForbidden();

    expect(Client::query()->where('name', 'Checkout')->exists())->toBeFalse();
});

it('lists only the clients of the addressed realm', function () {
    $globex = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex', 'domain' => 'globex.test']);
    realmClient($globex, 'Globex app');

    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->getJson(clientsUrl())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Partner portal')
        ->assertJsonPath('data.0.realm', 'acme')
        ->assertJsonMissingPath('data.0.secret')
        ->assertDontSee((string) $this->client->secret);
});

it('filters the list by whether a client is blocked', function () {
    realmClient($this->acme, 'Retired app')->forceFill(['revoked_at' => now()])->save();

    $token = managementApiToken($this, ManagementScope::ClientsRead);

    $this->withToken($token)->getJson(clientsUrl().'?filter[revoked]=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Retired app');

    auth()->forgetGuards();

    $this->withToken($token)->getJson(clientsUrl().'?filter[revoked]=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Partner portal');
});

it('shows a client by its client id without disclosing credentials', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->getJson(clientsUrl('acme', '/'.$this->client->client_id))
        ->assertOk()
        ->assertJsonPath('data.client_id', $this->client->client_id)
        ->assertJsonPath('data.confidential', true)
        ->assertJsonMissingPath('data.secret')
        ->assertDontSee((string) $this->client->secret)
        ->assertJsonPath('data.revoked', false);
});

it('does not reach a client through another realm', function () {
    Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex', 'domain' => 'globex.test']);

    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->getJson(clientsUrl('globex', '/'.$this->client->client_id))
        ->assertNotFound();
});

it('creates a confidential client and returns its secret', function () {
    $response = $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), clientPayload())
        ->assertCreated()
        ->assertJsonPath('data.client.name', 'Checkout')
        ->assertJsonPath('data.client.confidential', true)
        ->assertJsonPath('data.client.redirect_uris', ['https://checkout.test/callback'])
        ->assertJsonPath('data.client.consent_required', true)
        ->assertJsonPath('data.client.revoked', false);

    $client = Client::query()->where('client_id', $response->json('data.client.client_id'))->sole();

    expect($client->realm)->toBe('acme')
        ->and($client->secret)->toBe($response->json('data.credentials.secret'));
});

it('rejects invalid client grants and redirects on the matching field', function (array $overrides, array $omitted, string $field) {
    $payload = array_diff_key(clientPayload($overrides), array_flip($omitted));

    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Client::query()->where('name', 'Checkout')->exists())->toBeFalse();
})->with([
    'refresh without authorization code' => [['grant_types' => ['refresh_token']], [], 'grant_types'],
    'empty redirects' => [['redirect_uris' => []], [], 'redirect_uris'],
    'omitted redirects' => [[], ['redirect_uris'], 'grant_types'],
    'relative redirect' => [['redirect_uris' => ['/callback']], [], 'redirect_uris'],
    'unknown grant' => [['grant_types' => ['password']], [], 'grant_types'],
]);

it('refuses a payload key that maps to no property', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), clientPayload(['client_id' => 'chosen-by-me']))
        ->assertUnprocessable();
});

it('keeps what an update leaves out', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->patchJson(clientsUrl('acme', '/'.$this->client->client_id), ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.redirect_uris', ['https://rp.test/callback']);

    expect($this->client->refresh()->grant_types)->toBe(['authorization_code', 'refresh_token']);
});

it('holds an update to the same invariants as a create', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->patchJson(clientsUrl('acme', '/'.$this->client->client_id), ['redirect_uris' => [], 'revoked' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('redirect_uris');

    expect($this->client->refresh()->redirect_uris)->toBe(['https://rp.test/callback'])
        ->and($this->client->revoked_at)->toBeNull();
});

it('drops the secret when a client is turned public', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->patchJson(clientsUrl('acme', '/'.$this->client->client_id), [
            'token_endpoint_auth_method' => TokenEndpointAuthMethod::None->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.confidential', false)
        ->assertJsonMissingPath('data.secret');

    expect($this->client->refresh()->secret)->toBeNull();
});

it('deletes a client', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->deleteJson(clientsUrl('acme', '/'.$this->client->client_id))
        ->assertNoContent();

    expect(Client::query()->where('client_id', $this->client->client_id)->exists())->toBeFalse();
});

it('answers on the master host only', function () {
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->getJson(realmUrl($this->acme, '/api/v1/realms/acme/clients'))
        ->assertNotFound();
});

it('discloses and rotates secrets only through explicit write-scoped operations', function () {
    $url = clientsUrl('acme', '/'.$this->client->client_id);
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))->postJson($url.'/secret')->assertForbidden();
    auth()->forgetGuards();
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))->postJson($url.'/rotate-secret')->assertForbidden();
    auth()->forgetGuards();

    $token = managementApiToken($this, ManagementScope::ClientsWrite);
    $original = $this->client->secret;
    $this->withToken($token)->postJson($url.'/secret')->assertOk()->assertJsonPath('data.secret', $original);
    auth()->forgetGuards();
    $response = $this->withToken($token)->postJson($url.'/rotate-secret')->assertOk();
    expect($this->client->refresh()->secret)->toBe($response->json('data.secret'))->not->toBe($original)
        ->and(AdminEvent::forRealm($this->acme)->where('type', ClientAdminEvent::ClientSecretRevealed->type())->sole()->subject_id)->toBe($this->client->id)
        ->and(AdminEvent::forRealm($this->acme)->where('type', ClientAdminEvent::ClientSecretRotated->type())->sole()->subject_id)->toBe($this->client->id);
});

it('refuses secret operations through a different realm or for public clients', function () {
    $other = Realm::factory()->create();
    $public = realmClient($this->acme, confidential: false);
    $token = managementApiToken($this, ManagementScope::ClientsWrite);

    foreach (['secret', 'rotate-secret'] as $operation) {
        $this->withToken($token)->postJson(clientsUrl($other->slug, '/'.$this->client->client_id.'/'.$operation))->assertNotFound();
        auth()->forgetGuards();
        $this->withToken($token)->postJson(clientsUrl('acme', '/'.$public->client_id.'/'.$operation))->assertNotFound();
        auth()->forgetGuards();
    }
});

it('creates a client with the requested revocation state', function (bool $revoked) {
    $response = $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), clientPayload(['revoked' => $revoked]))
        ->assertCreated()
        ->assertJsonPath('data.client.revoked', $revoked);

    $client = Client::query()->where('client_id', $response->json('data.client.client_id'))->sole();

    expect($client->revoked_at !== null)->toBe($revoked)
        ->and(AdminEvent::forRealm($this->acme)->where('type', ClientAdminEvent::ClientRevoked->type())->count())->toBe($revoked ? 1 : 0);
})->with([true, false]);

it('updates client revocation without losing configuration and records each transition once', function () {
    $token = managementApiToken($this, ManagementScope::ClientsWrite);
    $url = clientsUrl('acme', '/'.$this->client->client_id);

    $this->withToken($token)->patchJson($url, ['revoked' => true])
        ->assertOk()->assertJsonPath('data.revoked', true);
    $revokedAt = $this->client->refresh()->revoked_at;
    $this->travel(1)->seconds();

    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.revoked', true);

    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, ['revoked' => true])
        ->assertOk()->assertJsonPath('data.revoked', true);

    expect($this->client->refresh()->revoked_at)->toEqual($revokedAt);

    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, ['revoked' => false])
        ->assertOk()
        ->assertJsonPath('data.revoked', false)
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.redirect_uris', ['https://rp.test/callback']);

    expect($this->client->refresh()->revoked_at)->toBeNull()
        ->and(AdminEvent::forRealm($this->acme)->where('type', ClientAdminEvent::ClientRevoked->type())->count())->toBe(1)
        ->and(AdminEvent::forRealm($this->acme)->where('type', ClientAdminEvent::ClientRestored->type())->count())->toBe(1);
});

it('rejects invalid revocation values without changing the client', function (mixed $revoked) {
    $token = managementApiToken($this, ManagementScope::ClientsWrite);
    $this->withToken($token)->postJson(clientsUrl(), clientPayload(['revoked' => $revoked]))
        ->assertUnprocessable()->assertJsonValidationErrors('revoked');

    auth()->forgetGuards();
    $this->withToken($token)->patchJson(clientsUrl('acme', '/'.$this->client->client_id), ['revoked' => $revoked, 'name' => 'Changed'])
        ->assertUnprocessable()->assertJsonValidationErrors('revoked');

    expect(Client::query()->where('name', 'Checkout')->exists())->toBeFalse()
        ->and($this->client->refresh()->name)->toBe('Partner portal')
        ->and($this->client->revoked_at)->toBeNull();
})->with([null, 'invalid', 2]);

it('refuses revocation without write scope or through another realm', function () {
    $url = clientsUrl('acme', '/'.$this->client->client_id);
    $this->withToken(managementApiToken($this, ManagementScope::ClientsRead))
        ->patchJson($url, ['revoked' => true])->assertForbidden();

    $other = Realm::factory()->create();
    auth()->forgetGuards();
    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->patchJson(clientsUrl($other->slug, '/'.$this->client->client_id), ['revoked' => true])->assertNotFound();

    expect($this->client->refresh()->revoked_at)->toBeNull();
});

function realmIssuer(Realm $realm): string
{
    return $realm->runAsCurrent(fn (): string => app(IssuerResolver::class)->url());
}

it('allows a client the realm audiences it lists, spelled as the token addresses them', function () {
    Resource::factory()->for($this->acme)->create(['identifier' => '/docpipe']);
    Resource::factory()->for($this->acme)->absolute('https://docpipe.example')->create();
    $audiences = [realmIssuer($this->acme).'/docpipe', 'https://docpipe.example', realmIssuer($this->acme)];

    $response = $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), clientPayload(['allowed_audiences' => $audiences]))
        ->assertCreated()
        ->assertJsonPath('data.client.allowed_audiences', $audiences);

    expect(Client::query()->where('client_id', $response->json('data.client.client_id'))->sole()->snapshot()->allowedAudiences)
        ->toBe($audiences);
});

it('rejects an allowed audience the realm does not answer to', function (string $audience) {
    Resource::factory()->for(Realm::master())->create(['identifier' => 'docpipe']);

    $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl(), clientPayload(['allowed_audiences' => [$audience]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('allowed_audiences');

    expect(Client::query()->where('name', 'Checkout')->exists())->toBeFalse();
})->with([
    'a relative identifier' => fn (): string => '/docpipe',
    'an unregistered resource' => fn (): string => realmIssuer(Realm::query()->where('slug', 'acme')->sole()).'/docpipe',
    'another realm\'s resource' => fn (): string => realmIssuer(Realm::master()).'/docpipe',
]);

it('replaces the allowed audiences on update and keeps those whose resource is gone', function () {
    Resource::factory()->for($this->acme)->create(['identifier' => 'docpipe']);
    $this->client->forceFill(['allowed_exchange_audiences' => ['https://retired.example']])->save();
    $url = clientsUrl('acme', '/'.$this->client->client_id);
    $token = managementApiToken($this, ManagementScope::ClientsWrite);
    $audiences = ['https://retired.example', realmIssuer($this->acme).'/docpipe'];

    $this->withToken($token)->patchJson($url, ['allowed_audiences' => $audiences])
        ->assertOk()
        ->assertJsonPath('data.allowed_audiences', $audiences);

    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, ['allowed_audiences' => [...$audiences, 'https://unknown.example']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('allowed_audiences');

    auth()->forgetGuards();
    $this->withToken($token)->patchJson($url, ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.allowed_audiences', $audiences);
});

it('binds a client credentials token to the allowed audience the client requests', function () {
    $docs = createRealm('Docs', 'docs', 'docs.test');
    Resource::factory()->for($docs)->create(['identifier' => '/docpipe']);
    $audience = realmIssuer($docs).'/docpipe';

    $created = $this->withToken(managementApiToken($this, ManagementScope::ClientsWrite))
        ->postJson(clientsUrl('docs'), clientPayload([
            'grant_types' => ['client_credentials'],
            'redirect_uris' => [],
            'allowed_audiences' => [$audience],
        ]))
        ->assertCreated();

    $token = $this->withBasicAuth($created->json('data.client.client_id'), $created->json('data.credentials.secret'))
        ->post(realmRoute($docs, 'oidc.token'), ['grant_type' => 'client_credentials', 'resource' => $audience])
        ->assertOk();

    expect(jwtClaims($token->json('access_token'))['aud'])->toBe($audience);
});
