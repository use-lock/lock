<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Audit\Models\AdminEvent;
use App\Auth\Models\User;
use App\Clients\Data\ClientAttributes;
use App\Clients\Enums\ClientAdminEvent;
use App\Clients\Ui\Actions\DeleteClient;
use App\Clients\Ui\Actions\RestoreClient;
use App\Clients\Ui\Actions\RevealClientSecret;
use App\Clients\Ui\Actions\RevokeClient;
use App\Clients\Ui\Actions\RotateClientSecret;
use App\Clients\Ui\Forms\CreateClientForm;
use App\Clients\Ui\Forms\UpdateClientForm;
use App\Clients\Ui\Tables\ClientsTable;
use App\Realms\Models\Realm;
use Closure;
use Illuminate\Validation\ValidationException;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\Client as ClientSnapshot;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;
use Lock\Server\Tokens\Models\AccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;
use function Tests\Helpers\realmClient;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->other = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientFormData(array $overrides = []): array
{
    return [
        'name' => 'Partner portal',
        'token_endpoint_auth_method' => TokenEndpointAuthMethod::ClientSecretBasic->value,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => [['uri' => 'https://portal.test/callback'], ['uri' => 'https://portal.test/alt']],
        'post_logout_redirect_uris' => [['uri' => 'https://portal.test/']],
        ...$overrides,
    ];
}

/**
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function clientSetting(Client $client, string $field, mixed $value): array
{
    return [[$field => $value], ['realm' => $client->realm, 'client' => $client->id, 'field' => $field]];
}

test('the clients console is forbidden without client scopes', function (Closure $user) {
    $client = realmClient($this->realm);
    $context = ['realm' => 'acme', 'client' => $client->id];

    $this->actingAs($user());
    $this->get('/admin/realms/acme/clients')->assertForbidden();
    $this->get("/admin/realms/acme/clients/{$client->id}")->assertForbidden();
    $this->loadDeniedTable(ClientsTable::class, context: ['realm' => 'acme'])->assertForbidden();
    $this->submitDeniedForm(CreateClientForm::class, clientFormData(), ['realm' => 'acme'])->assertForbidden();
    $this->submitDeniedForm(UpdateClientForm::class, ...clientSetting($client, 'name', 'Renamed'))->assertForbidden();
    $this->callDeniedAction(RotateClientSecret::class, [], $context)->assertForbidden();
    $this->callDeniedAction(RevokeClient::class, [], $context)->assertForbidden();
    $this->callDeniedAction(DeleteClient::class, [], $context)->assertForbidden();

    expect(Client::query()->count())->toBe(1);
})->with([
    'support admin' => fn () => globalAdmin(ManagementRoles::SUPPORT),
    'realm user' => fn () => User::factory()->create(),
]);

test('the clients table lists only the selected realm and searches name and client id', function () {
    $byName = realmClient($this->realm, 'Needle app');
    $byClientId = realmClient($this->realm, 'Other app');
    $byClientId->forceFill(['client_id' => 'needle-client'])->save();
    realmClient($this->realm, 'Unrelated app');
    realmClient($this->other, 'Needle elsewhere');

    $rows = $this->actingAs(globalAdmin())->loadTable(ClientsTable::class, ['q' => 'needle'], ['realm' => 'acme'])->assertOk()->json('data');

    expect(collect(is_array($rows) ? $rows : [])->pluck('id')->all())->toEqualCanonicalizing([$byName->id, $byClientId->id]);
});

test('a client row refuses a field the client does not have', function () {
    $client = realmClient($this->realm);

    $this->actingAs(globalAdmin());

    expect(fn () => $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'client_id', 'injected')))
        ->toThrow(NotFoundHttpException::class)
        ->and($client->refresh()->client_id)->not->toBe('injected');
});

test('a client detail page opens under its own realm only', function () {
    $client = realmClient($this->realm);

    $this->actingAs(globalAdmin());
    $this->get("/admin/realms/globex/clients/{$client->id}")->assertNotFound();
    $this->submitDeniedForm(UpdateClientForm::class, ['name' => 'Renamed'], ['realm' => 'globex', 'client' => $client->id, 'field' => 'name'])->assertForbidden();
    $this->callDeniedAction(RevokeClient::class, [], ['realm' => 'globex', 'client' => $client->id])->assertForbidden();
    $this->callDeniedAction(DeleteClient::class, [], ['realm' => 'globex', 'client' => $client->id])->assertForbidden();

    expect($client->refresh()->snapshot()->revoked)->toBeFalse()
        ->and($client->name)->toBe('Existing app');
});

test('creating a confidential client stores its configuration and audits the creation', function () {
    $this->realm->writeSettings(['default_scopes' => ['openid', 'profile']]);

    $this->actingAs(globalAdmin())->submitForm(CreateClientForm::class, clientFormData(), ['realm' => 'acme'])->assertRedirect();

    $client = Client::query()->where('name', 'Partner portal')->sole();
    expect($client->secret)->not->toBeNull()
        ->and($client->realm)->toBe('acme')
        ->and($client->token_endpoint_auth_method)->toBe(TokenEndpointAuthMethod::ClientSecretBasic)
        ->and($client->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($client->redirect_uris)->toBe(['https://portal.test/callback', 'https://portal.test/alt'])
        ->and($client->post_logout_redirect_uris)->toBe(['https://portal.test/'])
        ->and($client->default_scopes)->toBe(['openid', 'profile']);

    $event = AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientCreated->type())->sole();

    expect($event->subject_id)->toBe($client->id)
        ->and(data_get($event->context, 'realm'))->toBe('acme')
        ->and(data_get($event->context, 'client_id'))->toBe($client->client_id);
});

test('a public client is created without a secret', function () {
    $this->actingAs(globalAdmin())->submitForm(CreateClientForm::class, clientFormData([
        'token_endpoint_auth_method' => TokenEndpointAuthMethod::None->value,
        'grant_types' => ['authorization_code'],
    ]), ['realm' => 'acme'])->assertRedirect();

    $client = Client::query()->where('name', 'Partner portal')->sole();

    expect($client->snapshot()->confidential)->toBeFalse()
        ->and($client->secret)->toBeNull();
});

test('a machine client only needs the client credentials grant', function () {
    $this->actingAs(globalAdmin())->submitForm(CreateClientForm::class, clientFormData([
        'token_endpoint_auth_method' => TokenEndpointAuthMethod::ClientSecretPost->value,
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'post_logout_redirect_uris' => [],
    ]), ['realm' => 'acme'])->assertRedirect();

    $client = Client::query()->where('name', 'Partner portal')->sole();

    expect($client->grant_types)->toBe(['client_credentials'])
        ->and($client->redirect_uris)->toBeEmpty()
        ->and($client->snapshot()->confidential)->toBeTrue();
});

test('rejects inconsistent client settings on the matching field', function (array $overrides, string $field) {
    $this->actingAs(globalAdmin())->submitForm(CreateClientForm::class, clientFormData($overrides), ['realm' => 'acme'])->assertInvalid([$field]);

    expect(Client::query()->exists())->toBeFalse();
})->with([
    'refresh token without authorization code' => [['grant_types' => ['refresh_token']], 'grant_types'],
    'client credentials on a public client' => [['token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'client_credentials']], 'grant_types'],
    'unknown grant type' => [['grant_types' => ['implicit']], 'grant_types'],
    'no redirect uri for authorization code' => [['redirect_uris' => []], 'redirect_uris'],
    'relative redirect uri' => [['redirect_uris' => [['uri' => '/callback']]], 'redirect_uris.0.uri'],
    'redirect uri with fragment' => [['redirect_uris' => [['uri' => 'https://portal.test/callback#frag']]], 'redirect_uris.0.uri'],
    'empty redirect row' => [['redirect_uris' => [['uri' => '']]], 'redirect_uris.0.uri'],
    'multiple uris in one row' => [['redirect_uris' => [['uri' => "https://portal.test/callback\nhttps://portal.test/alt"]]], 'redirect_uris.0.uri'],
    'invalid post-logout uri' => [['post_logout_redirect_uris' => [['uri' => 'not a uri']]], 'post_logout_redirect_uris.0.uri'],
]);

test('each client row saves on its own and leaves the rest of the client alone', function () {
    $client = realmClient($this->realm);

    $this->actingAs(globalAdmin());
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'name', 'Renamed app'))
        ->assertRedirect("/admin/realms/acme/clients/{$client->id}");
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'grant_types', ['authorization_code']))->assertRedirect();
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'consent_required', false))->assertRedirect();
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'backchannel_logout_uri', 'https://rp.test/backchannel'))->assertRedirect();

    $client->refresh();

    expect($client->name)->toBe('Renamed app')
        ->and($client->grant_types)->toBe(['authorization_code'])
        ->and($client->redirect_uris)->toBe(['https://rp.test/callback'])
        ->and($client->consent_required)->toBeFalse()
        ->and($client->backchannel_logout_uri)->toBe('https://rp.test/backchannel')
        ->and($client->snapshot()->confidential)->toBeTrue();

    $changes = AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientUpdated->type())->get()
        ->flatMap(fn (AdminEvent $event): array => (array) data_get($event->context, 'changes'));

    expect($changes['name']['new'])->toBe('Renamed app')
        ->and($changes['consent_required']['new'])->toBeFalse();
});

test('a single client row is validated against the stored client', function () {
    $client = realmClient($this->realm);

    $this->actingAs(globalAdmin());
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'grant_types', ['refresh_token']))
        ->assertInvalid(['grant_types' => __('clients.validation.refresh-token-needs-code')]);
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'redirect_uris', []))
        ->assertInvalid(['redirect_uris' => __('clients.validation.redirect-uri-required')]);
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'grant_types', ['authorization_code', 'client_credentials']))->assertRedirect();
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'token_endpoint_auth_method', 'none'))
        ->assertInvalid(['token_endpoint_auth_method' => __('clients.validation.client-credentials-needs-secret')]);

    expect($client->refresh()->snapshot()->confidential)->toBeTrue()
        ->and($client->redirect_uris)->toBe(['https://rp.test/callback'])
        ->and($client->grant_types)->toBe(['authorization_code', 'client_credentials']);
});

test('turning a public client confidential mints a secret and back again drops it', function () {
    $client = realmClient($this->realm, confidential: false);

    $this->actingAs(globalAdmin());
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'token_endpoint_auth_method', 'client_secret_post'))->assertRedirect();

    expect($client->refresh()->snapshot()->confidential)->toBeTrue()
        ->and($client->secret)->not->toBeNull();

    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'token_endpoint_auth_method', 'none'))->assertRedirect();

    expect($client->refresh()->snapshot()->confidential)->toBeFalse()
        ->and($client->secret)->toBeNull();
});

test('rotating the secret replaces it and audits the rotation without disclosing it on the page', function () {
    $client = realmClient($this->realm);
    $previousSecret = $client->secret;

    $this->actingAs(globalAdmin())->callAction(RotateClientSecret::class, [], ['realm' => 'acme', 'client' => $client->id])
        ->assertRedirectsToRoute('admin.realms.clients.show', ['realm' => 'acme', 'client' => $client->id]);

    $currentSecret = $client->refresh()->secret;
    expect($currentSecret)->not->toBeNull()->not->toBe($previousSecret);

    $event = AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientSecretRotated->type())->sole();
    expect($event->subject_id)->toBe($client->id)
        ->and($event->toJson())->not->toContain($currentSecret);
});

test('a public client has no secret to rotate', function () {
    $client = realmClient($this->realm, confidential: false);

    $this->actingAs(globalAdmin())->callDeniedAction(RotateClientSecret::class, [], ['realm' => 'acme', 'client' => $client->id])->assertForbidden();
});

test('blocking a client stops it from being resolved as active until it is unblocked', function () {
    $client = realmClient($this->realm);
    $context = ['realm' => 'acme', 'client' => $client->id];
    $active = fn (): ?ClientSnapshot => $this->realm->runAsCurrent(fn () => app(ClientRepository::class)->findActive($client->client_id));

    $this->actingAs(globalAdmin());
    $this->callDeniedAction(RestoreClient::class, [], $context)->assertForbidden();
    $this->callAction(RevokeClient::class, [], $context)->assertReloadsPage();

    expect($client->refresh()->snapshot()->revoked)->toBeTrue()
        ->and($active())->toBeNull()
        ->and(AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientRevoked->type())->sole()->subject_id)->toBe($client->id);

    $this->callDeniedAction(RevokeClient::class, [], $context)->assertForbidden();
    $this->callAction(RestoreClient::class, [], $context)->assertReloadsPage();

    expect($client->refresh()->snapshot()->revoked)->toBeFalse()
        ->and($active())->not->toBeNull()
        ->and(AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientRestored->type())->sole()->subject_id)->toBe($client->id);
});

test('deleting a client removes what was issued to it and records the deletion', function () {
    $client = realmClient($this->realm);
    $survivor = realmClient($this->realm, 'Survivor app');
    $user = User::factory()->for($this->realm)->create();
    AccessToken::factory()->forClient($client)->forUser($user)->create();
    AccessToken::factory()->forClient($survivor)->forUser($user)->create();

    $this->actingAs(globalAdmin())->callAction(DeleteClient::class, [], ['realm' => 'acme', 'client' => $client->id])
        ->assertRedirectsToRoute('admin.realms.clients', ['realm' => 'acme']);

    expect(Client::query()->pluck('id')->all())->toContain($survivor->id)->not->toContain($client->id)
        ->and(AccessToken::query()->pluck('client_id')->all())->toBe([$survivor->id])
        ->and(data_get(AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientDeleted->type())->sole()->context, 'client_id'))->toBe($client->client_id);
});

test('redirect uri rows replace stored lists and optional lists can be cleared', function () {
    $client = realmClient($this->realm);
    $this->actingAs(globalAdmin());

    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'redirect_uris', [
        ['uri' => 'https://portal.test/new'],
        ['uri' => 'https://portal.test/second'],
    ]))->assertRedirect();
    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'post_logout_redirect_uris', [
        ['uri' => 'https://portal.test/signed-out'],
    ]))->assertRedirect();

    expect($client->refresh()->redirect_uris)->toBe(['https://portal.test/new', 'https://portal.test/second'])
        ->and($client->post_logout_redirect_uris)->toBe(['https://portal.test/signed-out']);

    $this->submitForm(UpdateClientForm::class, ...clientSetting($client, 'post_logout_redirect_uris', []))->assertRedirect();

    expect($client->refresh()->post_logout_redirect_uris)->toBeEmpty()
        ->and($client->redirect_uris)->toBe(['https://portal.test/new', 'https://portal.test/second']);
});

test('the initial client page is secret-free and revealing requires write access in the correct realm', function () {
    $client = realmClient($this->realm);
    $context = ['realm' => 'acme', 'client' => $client->id];
    $secret = $client->secret;
    expect($secret)->not->toBeNull();

    $this->actingAs(globalAdminWith(ManagementScope::ClientsRead))
        ->get("/admin/realms/acme/clients/{$client->id}")->assertOk()->assertDontSee((string) $secret);
    $this->callDeniedAction(RevealClientSecret::class, [], $context)->assertForbidden();

    $this->actingAs(globalAdmin());
    $this->get("/admin/realms/acme/clients/{$client->id}")->assertOk()->assertDontSee((string) $secret);
    $this->callDeniedAction(RevealClientSecret::class, [], ['realm' => 'globex', 'client' => $client->id])->assertForbidden();
    expect(AdminEvent::where('type', ClientAdminEvent::ClientSecretRevealed->type())->exists())->toBeFalse();

    $this->callAction(RevealClientSecret::class, [], $context)->assertOk()->assertSee((string) $secret);
    $event = AdminEvent::forRealm($this->realm)->where('type', ClientAdminEvent::ClientSecretRevealed->type())->sole();
    expect($event->subject_id)->toBe($client->id)->and($event->toJson())->not->toContain((string) $secret);
});

test('domain client configuration refuses unknown grants and excessive uri lists', function (array $overrides) {
    expect(fn () => new ClientAttributes(...[
        'name' => 'Invalid client',
        'tokenEndpointAuthMethod' => TokenEndpointAuthMethod::ClientSecretBasic,
        'redirectUris' => ['https://rp.test/callback'],
        'postLogoutRedirectUris' => [],
        'grantTypes' => ['authorization_code'],
        ...$overrides,
    ]))->toThrow(ValidationException::class);
})->with([
    'mixed known and unknown grants' => [['grantTypes' => ['authorization_code', 'implicit']]],
    'uri limit' => [['redirectUris' => array_fill(0, 101, 'https://rp.test/callback')]],
]);

test('the console enforces the uri list limit without replacing the stored list', function () {
    $client = realmClient($this->realm);

    $this->actingAs(globalAdmin())->submitForm(UpdateClientForm::class, ...clientSetting(
        $client, 'redirect_uris', array_fill(0, 101, ['uri' => 'https://rp.test/callback']),
    ))->assertInvalid(['redirect_uris']);

    expect($client->refresh()->redirect_uris)->toBe(['https://rp.test/callback']);
});
