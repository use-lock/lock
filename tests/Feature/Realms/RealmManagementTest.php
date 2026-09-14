<?php

declare(strict_types=1);

use App\Audit\Models\AdminEvent;
use App\Auth\Models\User;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Ui\Forms\CreateRealmForm;
use App\Realms\Ui\Forms\DeleteRealmForm;
use App\Realms\Ui\Forms\RealmSettingForm;
use App\Realms\Ui\Forms\UpdateRealmForm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Sessions\Models\OidcSession;
use Lock\Server\Shared\Realms\Settings\LoginMethod;
use Lock\Server\Shared\Realms\Settings\MfaRequirement;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\realmClient;
use function Tests\Helpers\realmSetting;

beforeEach(function () {
    $this->actingAs(globalAdmin());
});

it('creates an independent realm with default configuration through the admin form', function () {
    Realm::master()->writeSettings(['access_token_lifetime' => 120]);

    domainReachesThisInstance();

    $this->submitForm(CreateRealmForm::class, ['name' => 'Partners', 'slug' => 'partners', 'domain' => 'auth.partners.test'])
        ->assertRedirect(route('admin.realms.settings', ['realm' => 'partners', 'tabs' => 'tokens']));

    $realm = Realm::query()->where('slug', 'partners')->sole();

    expect($realm->name)->toBe('Partners')
        ->and($realm->domain)->toBe('auth.partners.test')
        ->and($realm->domain_status)->toBe(RealmDomainStatus::Verified)
        ->and($realm->domain_checked_at)->not->toBeNull()
        ->and($realm->tokens()->accessTokenLifetime)->toBe(900)
        ->and(AdminEvent::global()->where('type', RealmAdminEvent::RealmCreated->type())->sole()->subject_id)->toBe($realm->id);
    Http::assertSent(fn ($request): bool => $request->url() === 'http://auth.partners.test/.well-known/lock/domain-check');
});

it('rejects duplicate and unsafe realm identifiers', function (string $slug) {
    $before = Realm::query()->count();

    $this->submitForm(CreateRealmForm::class, ['name' => 'Invalid realm', 'slug' => $slug, 'domain' => 'invalid.example.com'])->assertInvalid(['slug']);

    expect(Realm::query()->count())->toBe($before);
})->with(['admin', '../admin', 'Uppercase', 'space name', 'trailing-']);

it('renames a realm without touching its identifier or configuration', function () {
    $realm = Realm::factory()->create(['name' => 'Partners', 'slug' => 'partners']);
    $realm->writeSettings(['access_token_lifetime' => 300]);

    $this->submitForm(UpdateRealmForm::class, ['name' => 'External partners'], ['realm' => $realm->slug])
        ->assertRedirect(route('admin.realms.settings', ['realm' => 'partners']));

    expect($realm->refresh()->name)->toBe('External partners')
        ->and($realm->slug)->toBe('partners')
        ->and($realm->tokens()->accessTokenLifetime)->toBe(300)
        ->and(data_get(AdminEvent::forRealm($realm)->where('type', RealmAdminEvent::RealmUpdated->type())->sole()->context, 'changes.name.new'))->toBe('External partners');
});

it('saves one setting at a time for the signed realm and leaves every other realm alone', function (string $slug) {
    $partner = Realm::factory()->create(['name' => 'Partners', 'slug' => 'partners']);
    $realm = $slug === 'admin' ? Realm::master() : $partner;
    $other = $slug === 'admin' ? $partner : Realm::master();

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'access_token_lifetime', 300))
        ->assertRedirect(route('admin.realms.settings', ['realm' => $slug, 'tabs' => 'tokens']));
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'login_methods', ['password', 'passkey']))
        ->assertRedirect(route('admin.realms.settings', ['realm' => $slug, 'tabs' => 'login']));
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'mfa_requirement', 'always'))
        ->assertRedirect(route('admin.realms.settings', ['realm' => $slug, 'tabs' => 'mfa']));
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'challenge_providers', ['totp']))
        ->assertRedirect(route('admin.realms.settings', ['realm' => $slug, 'tabs' => 'mfa']));
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'dynamic_registration', true))
        ->assertRedirect(route('admin.realms.settings', ['realm' => $slug, 'tabs' => 'clients']));
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'allowed_redirect_domains', "partner.test\n localhost \n"))->assertRedirect();
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'default_scopes', "openid\nprofile"))->assertRedirect();

    $realm->refresh();

    expect($realm->name)->toBe($slug === 'admin' ? 'Admin' : 'Partners')
        ->and($realm->slug)->toBe($slug)
        ->and($realm->tokens()->accessTokenLifetime)->toBe(300)
        ->and($realm->authentication()->methods)->toBe([LoginMethod::Password, LoginMethod::Passkey])
        ->and($realm->authentication()->mfa)->toBe(MfaRequirement::Always)
        ->and($other->authentication()->mfa)->toBe(MfaRequirement::IfEnrolled)
        ->and($realm->credentials()->challengeProviders)->toBe(['totp'])
        ->and($realm->clients()->dynamicRegistration)->toBeTrue()
        ->and($realm->clients()->allowedRedirectDomains)->toBe(['partner.test', 'localhost'])
        ->and($realm->clients()->defaultScopes)->toBe(['openid', 'profile'])
        ->and($other->refresh()->tokens()->accessTokenLifetime)->toBe(900);

    $event = AdminEvent::forRealm($realm)
        ->where('type', RealmAdminEvent::RealmUpdated->type())
        ->get()
        ->sole(fn (AdminEvent $event): bool => data_get($event->context, 'changes.access_token_lifetime') !== null);

    expect($event->subject_id)->toBe($realm->id)
        ->and(data_get($event->context, 'changes.access_token_lifetime'))->toBe(['old' => 900, 'new' => 300]);
})->with(['admin', 'partners']);

it('refuses a field the realm configuration does not define', function (string $field) {
    $realm = Realm::master();
    $before = $realm->configuration();

    expect(fn () => $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, $field, 'injected')))
        ->toThrow(NotFoundHttpException::class)
        ->and($realm->refresh()->configuration())->toBe($before)
        ->and($realm->name)->toBe('Admin')
        ->and($realm->slug)->toBe('admin');
})->with(['first_party_client_id', 'slug', 'name']);

it('saves the password policy of the signed realm and audits the change', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $other = Realm::master();

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'password_min_length', 12))->assertRedirect();
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'password_mixed_case', true))->assertRedirect();
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'password_numbers', true))->assertRedirect();
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'password_history', 5))->assertRedirect();
    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'password_max_age_days', 90))->assertRedirect();

    $policy = $realm->refresh()->credentials()->password;

    expect($policy->minLength)->toBe(12)
        ->and($policy->mixedCase)->toBeTrue()
        ->and($policy->numbers)->toBeTrue()
        ->and($policy->symbols)->toBeFalse()
        ->and($policy->history)->toBe(5)
        ->and($policy->maxAgeDays)->toBe(90)
        ->and($other->credentials()->password->minLength)->toBe(8)
        ->and($other->credentials()->password->maxAgeDays)->toBeNull();

    $changes = AdminEvent::forRealm($realm)->where('type', RealmAdminEvent::RealmUpdated->type())->get()
        ->flatMap(fn (AdminEvent $event): array => (array) data_get($event->context, 'changes'));

    expect($changes['password_min_length'])->toBe(['old' => 8, 'new' => 12])
        ->and($changes['password_max_age_days'])->toBe(['old' => 0, 'new' => 90]);
});

it('rejects an out-of-range password policy without saving it', function (string $field, int $value) {
    $realm = Realm::master();
    $before = $realm->configuration();

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, $field, $value))->assertInvalid([$field]);

    expect($realm->refresh()->configuration())->toBe($before);
})->with([
    'too short a minimum' => ['password_min_length', 5],
    'negative history' => ['password_history', -1],
    'negative age' => ['password_max_age_days', -1],
]);

it('reports a conflict with a setting the form does not show on the field being edited', function () {
    $realm = Realm::master();

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'session_token_ttl', 60))
        ->assertInvalid(['session_token_ttl' => __('realms.fields.session-token-refresh-skew.label')]);

    expect($realm->refresh()->sessions()->tokenTtl)->toBe(3600)
        ->and(AdminEvent::query()->where('type', RealmAdminEvent::RealmUpdated->type())->exists())->toBeFalse();
});

it('reports invalid list entries on the matching configuration field', function (string $field, string $value) {
    $realm = Realm::master();
    $before = $realm->configuration();

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, $field, $value))->assertInvalid([$field]);

    expect($realm->refresh()->configuration())->toBe($before);
})->with([
    'unknown scope' => ['default_scopes', 'admin:realms:manage'],
    'wildcard is not a default scope' => ['default_scopes', '*'],
    'unsafe scheme' => ['allowed_redirect_schemes', 'javascript'],
    'URL instead of host' => ['allowed_redirect_domains', 'https://partner.test/path'],
    'unknown trusted client' => ['trusted_clients', 'missing-client'],
    'unknown mfa requirement' => ['mfa_requirement', 'sometimes'],
]);

it('only trusts active clients belonging to the configured realm', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $master = Realm::master();
    $client = realmClient($master, 'Master client');

    $this->submitForm(RealmSettingForm::class, ...realmSetting($realm, 'trusted_clients', $client->client_id))->assertInvalid(['trusted_clients']);
    $this->submitForm(RealmSettingForm::class, ...realmSetting($master, 'trusted_clients', $client->client_id))->assertRedirect()->assertValid();

    expect($master->refresh()->clients()->trustedClients)->toBe([$client->client_id]);
});

it('deletes a realm with its users, clients, sessions, roles and resources and frees the identifier', function () {
    $realm = Realm::factory()->create(['name' => 'Partners', 'slug' => 'partners']);
    $user = User::factory()->for($realm)->create();
    $role = Role::factory()->for($realm)->create(['name' => 'member']);
    $user->roles()->attach($role);
    $keptRole = Role::factory()->for(Realm::master())->create(['name' => 'elsewhere']);
    $resource = Resource::factory()->for($realm)->create();
    $scope = ResourceScope::factory()->for($resource)->create();
    $client = realmClient($realm, 'Partner app');
    OidcSession::factory()->forUser($user)->create(['realm' => 'partners']);
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'departing-browser', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $user->notifications()->create(['id' => Str::uuid()->toString(), 'type' => 'test', 'data' => ['message' => 'departing']]);
    $masterClient = realmClient(Realm::master(), 'Admin app');

    $this->submitForm(DeleteRealmForm::class, ['name' => 'Partners'], ['realm' => $realm->slug])
        ->assertRedirect(route('admin.realms'));

    expect(Realm::query()->where('slug', 'partners')->exists())->toBeFalse()
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->notifications()->exists())->toBeFalse()
        ->and(Client::query()->whereKey($client->id)->exists())->toBeFalse()
        ->and(Client::query()->whereKey($masterClient->id)->exists())->toBeTrue()
        ->and(OidcSession::query()->where('realm', 'partners')->exists())->toBeFalse()
        ->and(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and(DB::table('role_user')->where('role_id', $role->id)->exists())->toBeFalse()
        ->and(Role::query()->whereKey($keptRole->id)->exists())->toBeTrue()
        ->and(Resource::query()->whereKey($resource->id)->exists())->toBeFalse()
        ->and(ResourceScope::query()->whereKey($scope->id)->exists())->toBeFalse()
        ->and(data_get(AdminEvent::global()->where('type', RealmAdminEvent::RealmDeleted->type())->sole()->context, 'slug'))->toBe('partners');

    domainReachesThisInstance();

    $this->submitForm(CreateRealmForm::class, ['name' => 'Partners again', 'slug' => 'partners', 'domain' => 'partners.example.com'])->assertRedirect();
});

it('requires the realm name to confirm a deletion', function () {
    $realm = Realm::factory()->create(['name' => 'Partners']);

    $this->submitForm(DeleteRealmForm::class, ['name' => 'Wrong name'], ['realm' => $realm->slug])->assertInvalid(['name']);

    expect(Realm::query()->whereKey($realm->id)->exists())->toBeTrue();
});

it('refuses to delete the admin realm', function () {
    $master = Realm::master();

    $this->submitDeniedForm(DeleteRealmForm::class, ['name' => $master->name], ['realm' => $master->slug])->assertForbidden();

    expect(Realm::query()->whereKey($master->id)->exists())->toBeTrue();
});
