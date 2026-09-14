<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Realms\Actions\CreateRealm;
use App\Realms\Data\CreateRealmData;
use App\Realms\Models\Realm;
use App\Realms\Support\DomainCheckToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;

/**
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function realmSetting(Realm $realm, string $field, mixed $value): array
{
    return [[$field => $value], ['realm' => $realm->slug, 'field' => $field]];
}

function realmUrl(Realm|string $realm, string $path = ''): string
{
    $realm = $realm instanceof Realm ? $realm : Realm::query()->where('slug', $realm)->sole();

    return $path === '' ? $realm->origin() : $realm->origin().'/'.ltrim($path, '/');
}

/**
 * @param  array<string, mixed>  $parameters
 */
function realmRoute(Realm|string $realm, string $name, array $parameters = []): string
{
    return realmUrl($realm, route($name, $parameters, absolute: false));
}

/**
 * Answers every domain check the way this instance answers it on its own
 * hosts, as if DNS and the proxy already led every domain here.
 */
function domainReachesThisInstance(): void
{
    Http::fake(fn (Request $request) => Http::response(DomainCheckToken::for((string) parse_url($request->url(), PHP_URL_HOST))));
}

function realmClient(Realm $realm, string $name = 'Existing app', bool $confidential = true): Client
{
    return $realm->runAsCurrent(fn (): Client => app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient($name, ['https://rp.test/callback'], $confidential));
}

/**
 * A realm built the way the console and the API build one, so it gets its own
 * signing keypair.
 *
 * @param  array<string, mixed>  $settings
 */
function createRealm(string $name, string $slug, string $domain, array $settings = []): Realm
{
    return app(CreateRealm::class)->handle(CreateRealmData::from([
        'name' => $name,
        'slug' => $slug,
        'domain' => $domain,
        'settings' => $settings,
    ]));
}
