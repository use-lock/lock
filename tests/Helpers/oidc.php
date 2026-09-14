<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Realms\Models\Realm;
use Illuminate\Testing\TestResponse;
use Lock\Laravel\Support\Facades\OidcClient;
use Lock\Laravel\Support\Testing\OidcClientFake;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Realms\IssuerResolver;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Keeps a login round-trip entirely in-process. */
function selfSsoProviderFake(?Client $client = null): OidcClientFake
{
    $client ??= app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'First-party app',
        [config('app.url').'/login/callback'],
    );

    config()->set([
        'oidc-client.issuer' => app(IssuerResolver::class)->url(),
        'oidc-client.client_id' => $client->client_id,
        'oidc-client.client_secret' => $client->secret,
    ]);

    $realm = Realm::master();
    $realm->first_party_client_id = $client->client_id;
    $realm->writeSettings(['first_party_trusted' => true]);

    return OidcClient::fake();
}

/**
 * @param  array<string, string>  $payload
 * @return TestResponse<Response>
 */
function clientCredentialsToken(TestCase $test, array $payload): TestResponse
{
    return $test->post(route('oidc.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $test->client->client_id,
        'client_secret' => $test->client->secret,
        ...$payload,
    ]);
}

/**
 * @return array<string, mixed>
 */
function jwtClaims(?string $jwt): array
{
    Assert::assertIsString($jwt, 'Expected a JWT string.');

    $segments = explode('.', $jwt);

    Assert::assertCount(3, $segments, 'Expected a compact JWS.');

    $payload = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true, flags: JSON_THROW_ON_ERROR);

    Assert::assertIsArray($payload, 'Expected a JWT payload object.');

    return $payload;
}
