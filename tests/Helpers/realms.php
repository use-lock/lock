<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Realms\Actions\CreateRealm;
use App\Realms\Actions\UpdateRealm;
use App\Realms\Data\CreateRealmData;
use App\Realms\Data\UpdateRealmData;
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

/**
 * @param  array<string, mixed>  $theme
 */
function themeRealm(Realm $realm, array $theme): Realm
{
    return app(UpdateRealm::class)->handle($realm, UpdateRealmData::from(['theme' => $theme]));
}

/**
 * One value of every shape a theme accepts, none of them any real realm's.
 *
 * @return array<string, string>
 */
function fullThemeValues(): array
{
    return [
        '--lt-bg' => '#101418',
        '--lt-fg' => '#eef2f6',
        '--lt-surface' => 'rgba(10, 12, 14, 0.8)',
        '--lt-primary' => '#3366cc',
        '--lt-primary-fg' => '#ffffff',
        '--lt-radius' => '3px',
        '--lt-font-sans' => "'Example Text', Georgia, serif",
        '--lt-font-display' => "'Example Display', Georgia, serif",
        '--lt-shadow-inner' => 'none',
        'logo-url' => 'asset:wordmark.svg',
        'logo-alt' => 'Emberfall',
        'logo-width' => '280px',
        'background-image-url' => 'https://cdn.example.com/art/key.webp',
        'background-color' => '#050608',
        'background-fit' => 'cover',
        'background-scrim' => 'radial-gradient(80% 70% at 50% 40%, rgba(1, 2, 3, 0.1) 20%, rgba(1, 2, 3, 0.7) 100%), linear-gradient(rgba(1, 2, 3, 0.3), rgba(1, 2, 3, 0.3))',
        'panel-blur' => '4px',
        'panel-width' => '460px',
        'text-shadow-heading' => '0 2px 12px #000',
        'label-font' => '700 12px/1 var(--lt-font-sans)',
        'label-transform' => 'uppercase',
        'button-primary-fill' => 'linear-gradient(180deg, #4477dd 0, #3366cc 50%, #2255aa 100%)',
        'button-primary-shadow' => 'inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 0 0 1px #112244, 0 8px 24px rgba(50, 100, 200, 0.4)',
        'button-hover-filter' => 'brightness(1.1)',
        'button-disabled-filter' => 'grayscale(0.5) brightness(0.7)',
        'input-border-style' => 'underline',
        'input-border-width' => '2px',
        'mail-from-name' => 'Emberfall',
        'mail-from-address' => 'hello@emberfall.example',
    ];
}
