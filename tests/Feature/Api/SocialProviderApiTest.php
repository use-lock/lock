<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use Dedoc\Scramble\Generator;
use Lock\Server\Brokering\Models\SocialAccount;

use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\realmUrl;

it('creates social providers without exposing their secrets', function (string $driver, array $config, string $secret) {
    $realm = Realm::factory()->create();

    $response = $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/social-providers"), [
            'key' => 'company-login', 'driver' => $driver, 'config' => $config, 'enabled' => true,
        ])->assertCreated()->assertJsonPath('data.key', 'company-login')
        ->assertJsonPath('data.config.client_id', 'client-id')
        ->assertJsonPath('data.callback_url', $realm->origin().'/auth/social/company-login/callback')
        ->assertJsonMissingPath('data.config.'.$secret)->assertDontSee($config[$secret]);

    $provider = $realm->socialProviders()->sole();
    expect($provider->id)->toBe($response->json('data.id'))
        ->and($provider->config)->toBe($config)
        ->and($provider->getRawOriginal('config'))->not->toContain($config[$secret])
        ->and(AdminEvent::forRealm($realm)->sole()->toJson())->not->toContain($config[$secret]);
})->with([
    'google' => ['google', ['client_id' => 'client-id', 'client_secret' => 'google-secret'], 'client_secret'],
    'github' => ['github', ['client_id' => 'client-id', 'client_secret' => 'github-secret'], 'client_secret'],
    'oidc' => ['oidc', ['client_id' => 'client-id', 'client_secret' => 'oidc-secret', 'issuer' => 'https://issuer.test'], 'client_secret'],
    'apple' => ['apple', ['client_id' => 'client-id', 'team_id' => 'team', 'key_id' => 'key', 'private_key' => 'private-key-secret'], 'private_key'],
]);

it('lists and shows only providers in the addressed realm without secrets', function () {
    $provider = RealmSocialProvider::factory()->create(['config' => ['client_id' => 'id', 'client_secret' => 'hidden-secret']]);
    RealmSocialProvider::factory()->for($provider->realm)->oidc()->create();
    RealmSocialProvider::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers");
    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead));

    $this->getJson($url.'?filter[driver]=google&sort=key&per_page=1')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $provider->id)->assertDontSee('hidden-secret');
    auth()->forgetGuards();
    $this->getJson($url.'/'.$provider->id)->assertOk()->assertJsonPath('data.id', $provider->id)
        ->assertJsonMissingPath('data.config.client_secret')->assertDontSee('hidden-secret');
});

it('partially updates credentials and preserves omitted or blank secrets', function (mixed $secret) {
    $provider = RealmSocialProvider::factory()->create(['config' => ['client_id' => 'old-id', 'client_secret' => 'keep-secret']]);
    $config = $secret === 'omitted' ? ['client_id' => 'new-id'] : ['client_id' => 'new-id', 'client_secret' => $secret];

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers/{$provider->id}"), [
            'config' => $config, 'enabled' => false,
        ])->assertOk()->assertJsonPath('data.enabled', false)->assertDontSee('keep-secret');

    expect($provider->refresh()->config)->toBe(['client_id' => 'new-id', 'client_secret' => 'keep-secret']);
})->with(['omitted', '', null]);

it('rotates secrets without disclosing them and treats an empty update as a no-op', function () {
    $provider = RealmSocialProvider::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers/{$provider->id}");
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite));

    $this->patchJson($url, ['config' => ['client_secret' => 'replacement-secret']])->assertOk()->assertDontSee('replacement-secret');
    auth()->forgetGuards();
    $this->patchJson($url, [])->assertOk();

    expect($provider->refresh()->config['client_secret'])->toBe('replacement-secret')
        ->and(AdminEvent::forRealm($provider->realm)->count())->toBe(1)
        ->and(AdminEvent::forRealm($provider->realm)->sole()->toJson())->not->toContain('replacement-secret');
});

it('rejects malformed provider creation payloads', function (array $payload, string $field) {
    $realm = Realm::factory()->create();
    $payload = array_replace(['key' => 'google', 'driver' => 'google', 'config' => ['client_id' => 'id', 'client_secret' => 'secret'], 'enabled' => true], $payload);

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$realm->slug}/social-providers"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($realm->socialProviders()->count())->toBe(0);
})->with([
    'invalid key' => [['key' => 'Bad Key'], 'key'],
    'unknown driver' => [['driver' => 'unknown'], 'driver'],
    'missing credential' => [['config' => ['client_id' => 'id']], 'config.client_secret'],
    'wrong credential type' => [['config' => ['client_id' => [], 'client_secret' => 'secret']], 'config.client_id'],
    'wrong driver credentials' => [['config' => ['client_id' => 'id', 'client_secret' => 'secret', 'private_key' => 'extra']], 'config'],
    'long credential' => [['config' => ['client_id' => str_repeat('a', 256), 'client_secret' => 'secret']], 'config.client_id'],
    'unknown property' => [['realm_id' => 'other'], 'realm_id'],
]);

it('rejects immutable fields and invalid partial credential updates', function (array $payload, string $field) {
    $provider = RealmSocialProvider::factory()->create();
    $original = $provider->config;

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers/{$provider->id}"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($provider->refresh()->config)->toBe($original);
})->with([
    'key' => [['key' => 'new'], 'key'],
    'driver' => [['driver' => 'oidc'], 'driver'],
    'empty client id' => [['config' => ['client_id' => '']], 'config.client_id'],
    'unknown config key' => [['config' => ['surprise' => 'value']], 'config.surprise'],
    'wrong secret type' => [['config' => ['client_secret' => ['bad']]], 'config.client_secret'],
]);

it('enforces provider key uniqueness within each realm', function () {
    $provider = RealmSocialProvider::factory()->create();
    $other = Realm::factory()->create();
    $payload = ['key' => 'google', 'driver' => 'google', 'config' => $provider->config, 'enabled' => true];
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite));

    $this->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('key');
    auth()->forgetGuards();
    $this->postJson(realmUrl(Realm::master(), "/api/v1/realms/{$other->slug}/social-providers"), $payload)->assertCreated();

    expect($other->socialProviders()->sole()->key)->toBe('google');
});

it('does not read or mutate providers through another realm', function (string $method) {
    $provider = RealmSocialProvider::factory()->create();
    $other = Realm::factory()->create();

    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead, ManagementScope::RealmsWrite))
        ->json($method, realmUrl(Realm::master(), "/api/v1/realms/{$other->slug}/social-providers/{$provider->id}"), ['enabled' => false])
        ->assertNotFound();

    expect($provider->refresh()->enabled)->toBeTrue();
})->with(['GET', 'PATCH', 'DELETE']);

it('deletes a provider and its realm-local social links', function () {
    $provider = RealmSocialProvider::factory()->create();
    $user = User::factory()->for($provider->realm)->create();
    $link = SocialAccount::factory()->create(['realm' => $provider->realm->slug, 'user_id' => $user->id, 'provider' => $provider->key]);
    $otherUser = User::factory()->create();
    $other = SocialAccount::factory()->create(['realm' => $otherUser->realm->slug, 'user_id' => $otherUser->id, 'provider' => $provider->key]);

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->deleteJson(realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers/{$provider->id}"))->assertNoContent();

    $this->assertModelMissing($provider);
    $this->assertModelMissing($link);
    $this->assertModelExists($other);
});

it('requires authentication and realm write scope', function () {
    $provider = RealmSocialProvider::factory()->create();
    $url = realmUrl(Realm::master(), "/api/v1/realms/{$provider->realm->slug}/social-providers/{$provider->id}");

    $this->getJson($url)->assertUnauthorized();
    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))->deleteJson($url)->assertForbidden();

    $this->assertModelExists($provider);
});

it('documents social provider operations and payloads for the API playground', function () {
    $document = app(Generator::class)();
    $collection = '/v1/realms/{realm}/social-providers';
    $item = $collection.'/{socialProvider}';

    foreach ([$collection, $item] as $path) {
        expect($document['paths'][$path]['get']['security'])->toBe([['oauth2' => ['realms:read']]]);
    }

    foreach (['post' => $collection, 'put' => $item, 'delete' => $item] as $method => $path) {
        expect($document['paths'][$path][$method]['security'])->toBe([['oauth2' => ['realms:write']]]);
    }

    expect($document['paths'][$collection]['post']['responses'])->toHaveKey('201')->not->toHaveKey('200')
        ->and($document['components']['schemas']['CreateSocialProviderData']['properties'])->toHaveKeys(['key', 'driver', 'config', 'enabled'])
        ->and($document['components']['schemas']['UpdateSocialProviderData']['required'] ?? [])->toBeEmpty()
        ->and($document['components']['schemas']['CreateSocialProviderData']['properties']['config']['$ref'])->toBe('#/components/schemas/SocialProviderCredentialsData')
        ->and($document['components']['schemas']['SocialProviderCredentialsData']['type'])->toBe('object')
        ->and($document['components']['schemas']['SocialProviderPublicConfigData']['properties'])->toHaveKey('client_id')->not->toHaveKeys(['client_secret', 'private_key']);
});
