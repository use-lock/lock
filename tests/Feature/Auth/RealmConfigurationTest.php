<?php

declare(strict_types=1);

use App\Realms\Models\Realm;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;

use function Tests\Helpers\createRealm;
use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\realmUrl;

it('serves only persisted realms and keeps their issuer URLs separate', function () {
    $this->getJson('http://partners.lock.test/.well-known/openid-configuration')->assertNotFound();

    domainReachesThisInstance();
    createRealm('Partners', 'partners', 'partners.lock.test');

    $this->getJson('http://partners.lock.test/.well-known/openid-configuration')
        ->assertOk()
        ->assertJsonPath('issuer', 'http://partners.lock.test')
        ->assertJsonPath('token_endpoint', 'http://partners.lock.test/oauth/token');
    $this->getJson(realmUrl(Realm::master(), '/.well-known/openid-configuration'))
        ->assertOk()->assertJsonPath('issuer', config('app.url'));
});

it('applies database registration rules only to the configured realm', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $realm->writeSettings([
        'dynamic_registration' => true,
        'allowed_redirect_domains' => ['partner.test'],
        'default_scopes' => ['openid', 'profile'],
        'optional_scopes' => [],
    ]);

    $this->postJson(realmUrl(Realm::master(), '/oauth/register'), ['redirect_uris' => ['https://partner.test/callback']])->assertNotFound();
    $this->postJson(realmUrl($realm, '/oauth/register'), ['redirect_uris' => ['https://other.test/callback']])
        ->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');

    $response = $this->postJson(realmUrl($realm, '/oauth/register'), ['redirect_uris' => ['https://partner.test/callback']])
        ->assertCreated()->assertJsonPath('scope', 'openid profile');

    expect(Client::query()->where('client_id', $response->json('client_id'))->sole()->realm)->toBe('partners');

    $realm->writeSettings(['dynamic_registration' => false]);

    $this->postJson(realmUrl($realm, '/oauth/register'), ['redirect_uris' => ['https://partner.test/callback']])->assertNotFound();
});

it('uses each realms persisted token lifetime and rejects clients from another realm', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $client = $realm->runAsCurrent(fn () => app(ClientRepository::class)->createClientCredentialsGrantClient('Partner service'));

    $credentials = [
        'grant_type' => 'client_credentials',
        'client_id' => $client->client_id,
        'client_secret' => $client->secret,
        'scope' => 'profile',
    ];

    $realm->writeSettings(['client_credentials_lifetime' => 120]);

    $this->postJson(realmUrl($realm, '/oauth/token'), $credentials)->assertOk()->assertJsonPath('expires_in', 120);
    $this->postJson(realmUrl(Realm::master(), '/oauth/token'), $credentials)->assertUnauthorized()->assertJsonPath('error', 'invalid_client');

    $realm->writeSettings(['client_credentials_lifetime' => 240]);

    $this->postJson(realmUrl($realm, '/oauth/token'), $credentials)->assertOk()->assertJsonPath('expires_in', 240);
});
