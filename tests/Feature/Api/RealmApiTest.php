<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmConfiguration;
use Illuminate\Support\Facades\Http;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\provisionManagementApi;
use function Tests\Helpers\realmUrl;

beforeEach(function () {
    domainReachesThisInstance();

    $this->acme = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
});

function realmsUrl(string $path = ''): string
{
    return realmUrl(Realm::master(), '/api/v1/realms'.$path);
}

it('refuses a request without a token', function () {
    $this->getJson(realmsUrl())->assertUnauthorized();
});

it('refuses a token that only grants the read scope for a write', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))
        ->postJson(realmsUrl(), ['name' => 'Globex', 'slug' => 'globex', 'domain' => 'globex.test'])
        ->assertForbidden();

    expect(Realm::query()->where('slug', 'globex')->exists())->toBeFalse();
});

it('lists every realm and filters by slug', function () {
    $token = managementApiToken($this, ManagementScope::RealmsRead);

    $this->withToken($token)->getJson(realmsUrl())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Acme');

    auth()->forgetGuards();

    $this->withToken($token)->getJson(realmsUrl().'?filter[slug]=acme')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Acme')
        ->assertJsonPath('data.0.domain', 'acme.test')
        ->assertJsonPath('data.0.master', false);
});

it('shows a realm by slug and reports the master realm as such', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))
        ->getJson(realmsUrl('/'.Realm::master()->slug))
        ->assertOk()
        ->assertJsonPath('data.master', true)
        ->assertJsonPath('data.domain', null)
        ->assertJsonPath('data.host', Realm::masterHost());
});

it('creates a realm with its own signing key and a verified domain', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmsUrl(), ['name' => 'Globex', 'slug' => 'globex', 'domain' => 'globex.test'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'globex')
        ->assertJsonPath('data.domain_status', RealmDomainStatus::Verified->value);
});

it('rejects a slug that is already taken', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmsUrl(), ['name' => 'Other Acme', 'slug' => 'acme', 'domain' => 'other.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('refuses to give the master realm a domain of its own', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/'.Realm::master()->slug), ['domain' => 'takeover.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('domain');

    expect(Realm::master()->domain)->toBeNull();
});

it('deletes a realm and frees its slug', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->deleteJson(realmsUrl('/acme'))
        ->assertNoContent();

    expect(Realm::query()->where('slug', 'acme')->exists())->toBeFalse();
});

it('refuses to delete the master realm', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->deleteJson(realmsUrl('/'.Realm::master()->slug))
        ->assertStatus(409);

    expect(Realm::query()->where('slug', Realm::master()->slug)->exists())->toBeTrue();
});

it('answers on the master host only', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))
        ->getJson(realmUrl($this->acme, '/api/v1/realms'))
        ->assertNotFound();
});

it('declares its scopes as the resource a token is addressed to', function () {
    provisionManagementApi();

    $this->getJson(realmUrl(Realm::master(), '/.well-known/oauth-protected-resource/api'))
        ->assertOk()
        ->assertJsonPath('scopes_supported', fn (array $scopes): bool => ! array_diff($scopes, ManagementScope::values()) && ! array_diff(ManagementScope::values(), $scopes));
});

it('reports the realm configuration with every setting typed', function () {
    $this->acme->writeSettings(['access_token_lifetime' => 900]);
    $this->acme->writeSettings(['login_methods' => ['passkey']]);

    $configuration = $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))
        ->getJson(realmsUrl('/acme'))
        ->assertOk()
        ->json('data.settings');

    expect(array_keys($configuration))->toEqualCanonicalizing(array_keys(RealmConfiguration::defaults()))
        ->and($configuration['access_token_lifetime'])->toBe(900)
        ->and($configuration['login_methods'])->toBe(['passkey'])
        ->and($configuration['password_mixed_case'])->toBeBool();
});

it('updates a subset of the configuration and leaves the rest alone', function () {
    $before = $this->acme->configuration();

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), ['settings' => [
            'access_token_lifetime' => 900,
            'mfa_requirement' => 'always',
            'login_methods' => ['password', 'passkey'],
        ]])
        ->assertOk()
        ->assertJsonPath('data.settings.access_token_lifetime', 900)
        ->assertJsonPath('data.settings.mfa_requirement', 'always');

    $after = $this->acme->refresh()->configuration();

    expect($after['login_methods'])->toBe(['password', 'passkey'])
        ->and($after['password_min_length'])->toBe($before['password_min_length'])
        ->and($after['refresh_token_lifetime'])->toBe($before['refresh_token_lifetime']);
});

it('stores a quoted number as the integer the setting holds', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), ['settings' => ['totp_window' => '4']])
        ->assertOk()
        ->assertJsonPath('data.settings.totp_window', 4);

    expect($this->acme->refresh()->configuration()['totp_window'])->toBe(4);
});

it('records a configuration change on the realm trail', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), ['settings' => ['recovery_codes' => 12]])
        ->assertOk();

    $event = AdminEvent::forRealm($this->acme)
        ->where('type', RealmAdminEvent::RealmUpdated->type())
        ->sole();

    expect(data_get($event->context, 'changes'))->toBe(['recovery_codes' => ['old' => 8, 'new' => 12]])
        ->and($event->user_id)->toBeNull();
});

it('rejects a setting that contradicts one it was not sent with', function () {
    $this->acme->writeSettings(['session_absolute_lifetime' => 3600]);

    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), ['settings' => ['session_token_ttl' => 7200]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.session_token_ttl');

    expect($this->acme->refresh()->configuration()['session_token_ttl'])->not->toBe(7200);
});

it('rejects a setting it does not know', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), ['settings' => ['make_me_admin' => true]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.make_me_admin');
});

it('creates a realm with a configuration and leaves the rest on the defaults', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmsUrl(), [
            'name' => 'Globex',
            'slug' => 'globex',
            'domain' => 'globex.test',
            'settings' => ['access_token_lifetime' => 900, 'login_methods' => ['passkey']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.settings.access_token_lifetime', 900)
        ->assertJsonPath('data.settings.login_methods', ['passkey']);

    $configuration = Realm::query()->where('slug', 'globex')->sole()->configuration();
    $defaults = RealmConfiguration::defaults();

    expect($configuration['access_token_lifetime'])->toBe(900)
        ->and($configuration['login_methods'])->toBe(['passkey'])
        ->and($configuration['password_min_length'])->toBe($defaults['password_min_length'])
        ->and($configuration['refresh_token_lifetime'])->toBe($defaults['refresh_token_lifetime']);
});

it('keeps no realm behind when the configuration it was created with is rejected', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmsUrl(), [
            'name' => 'Globex',
            'slug' => 'globex',
            'domain' => 'globex.test',
            'settings' => ['session_token_ttl' => 31536000],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.session_token_ttl');

    expect(Realm::query()->where('slug', 'globex')->exists())->toBeFalse();
});

it('records the settings a realm was created with', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmsUrl(), [
            'name' => 'Globex',
            'slug' => 'globex',
            'domain' => 'globex.test',
            'settings' => ['recovery_codes' => 12],
        ])
        ->assertCreated();

    $event = AdminEvent::where('type', RealmAdminEvent::RealmCreated->type())->sole();

    expect(data_get($event->context, 'settings'))->toBe(['recovery_codes' => 12]);
});

it('applies a name, settings and a new domain in one request', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), [
            'name' => 'Acme Inc',
            'domain' => 'acme.example',
            'settings' => ['recovery_codes' => 12],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Inc')
        ->assertJsonPath('data.domain', 'acme.example')
        ->assertJsonPath('data.domain_status', RealmDomainStatus::Verified->value)
        ->assertJsonPath('data.settings.recovery_codes', 12);

    $trail = AdminEvent::forRealm($this->acme)->where('type', RealmAdminEvent::RealmUpdated->type())->get();

    expect($this->acme->refresh()->name)->toBe('Acme Inc')
        ->and($this->acme->domain)->toBe('acme.example')
        ->and($trail)->toHaveCount(2)
        ->and($trail->flatMap(fn (AdminEvent $entry): array => array_keys((array) data_get($entry->context, 'changes')))->all())
        ->toEqualCanonicalizing(['name', 'recovery_codes', 'domain']);
});

it('refuses a domain that is not lowercase on both paths', function () {
    $token = managementApiToken($this, ManagementScope::RealmsWrite);

    $this->withToken($token)
        ->postJson(realmsUrl(), ['name' => 'Globex', 'slug' => 'globex', 'domain' => 'Globex.Test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('domain');

    auth()->forgetGuards();

    $this->withToken($token)
        ->patchJson(realmsUrl('/acme'), ['domain' => 'Acme2.Test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('domain');

    expect(Realm::query()->where('slug', 'globex')->exists())->toBeFalse()
        ->and($this->acme->refresh()->domain)->toBe('acme.test');
});

it('refuses the whole request when one of its parts is rejected', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(realmsUrl('/acme'), [
            'name' => 'Acme Inc',
            'settings' => ['mfa_requirement' => 'whenever'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.mfa_requirement');

    expect($this->acme->refresh()->name)->toBe('Acme');
});

it('does not recheck an unchanged domain or a settings-only update', function () {
    $token = managementApiToken($this, ManagementScope::RealmsWrite);
    $this->withToken($token)->patchJson(realmsUrl('/acme'), ['domain' => 'acme.test'])->assertOk();
    auth()->forgetGuards();
    $this->withToken($token)->patchJson(realmsUrl('/acme'), ['name' => 'Renamed'])->assertOk();

    Http::assertNothingSent();
});
