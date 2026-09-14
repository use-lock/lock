<?php

declare(strict_types=1);

use App\Audit\Models\AdminEvent;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Ui\Actions\CheckRealmDomainAction;
use App\Realms\Ui\Forms\CreateRealmForm;
use App\Realms\Ui\Forms\UpdateRealmDomainForm;
use Illuminate\Support\Facades\Http;
use Lattice\Ui\Enums\Variant;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\globalAdmin;

beforeEach(function () {
    config(['app.url' => 'http://console.example.test']);

    $this->actingAs(globalAdmin());
});

it('keeps a realm whose domain does not reach this instance yet and says why', function (Closure $answer, RealmDomainStatus $status) {
    Http::fake(['*' => $answer]);

    $this->submitForm(CreateRealmForm::class, ['name' => 'Partners', 'slug' => 'partners', 'domain' => 'auth.partners.test'])
        ->assertRedirect(route('admin.realms.settings', ['realm' => 'partners']));

    $this->assertLatticeEffects($this->get(route('admin.realms.settings', ['realm' => 'partners'])))
        ->assertFlashed('toast', fn (array $toast) => expect($toast)->toMatchArray([
            'variant' => Variant::Warning->value,
            'message' => __('realms.created-unverified', [
                'outcome' => __('realms.domain.outcome.'.$status->value, ['domain' => 'auth.partners.test']),
            ]),
        ]));

    expect(Realm::query()->where('slug', 'partners')->sole()->domain_status)->toBe($status);
})->with([
    'no answer' => [fn () => Http::failedConnection(), RealmDomainStatus::Unreachable],
    'another server' => [fn () => Http::response('not found', 404), RealmDomainStatus::Misrouted],
    'another Lock' => [fn () => Http::response(str_repeat('0', 64)), RealmDomainStatus::Misrouted],
]);

it('refuses a domain no realm may take', function (string $domain) {
    Realm::factory()->create(['domain' => 'taken.example.com']);
    $before = Realm::query()->count();

    $this->submitForm(CreateRealmForm::class, ['name' => 'Partners', 'slug' => 'partners', 'domain' => $domain])->assertInvalid(['domain']);

    expect(Realm::query()->count())->toBe($before);
})->with([
    'taken by another realm' => 'taken.example.com',
    'the console host' => 'console.example.test',
    'an IP address' => '203.0.113.7',
    'a URL' => 'https://auth.example.com',
    'a port' => 'auth.example.com:8443',
    'a single label' => 'localhost',
    'uppercase' => 'Auth.Example.com',
]);

it('moves a realm to another domain, which starts over and is checked right away', function () {
    $realm = Realm::factory()->create(['domain' => 'old.partners.test', 'domain_status' => RealmDomainStatus::Verified]);
    Http::fake(['*' => Http::failedConnection()]);

    $this->submitForm(UpdateRealmDomainForm::class, ['domain' => 'new.partners.test'], ['realm' => $realm->slug])
        ->assertRedirect(route('admin.realms.settings', ['realm' => $realm->slug]));

    expect($realm->refresh()->domain)->toBe('new.partners.test')
        ->and($realm->domain_status)->toBe(RealmDomainStatus::Unreachable)
        ->and(data_get(AdminEvent::forRealm($realm)->where('type', RealmAdminEvent::RealmUpdated->type())->sole()->context, 'changes.domain'))
        ->toBe(['old' => 'old.partners.test', 'new' => 'new.partners.test']);
});

it('checks a domain again on request', function () {
    $realm = Realm::factory()->create(['domain_status' => RealmDomainStatus::Unreachable]);
    domainReachesThisInstance();

    $this->callAction(CheckRealmDomainAction::class, [], ['realm' => $realm->slug])
        ->assertToast(Variant::Success)
        ->assertReloadsPage();

    expect($realm->refresh()->domain_status)->toBe(RealmDomainStatus::Verified);
});

it('leaves the host of the master realm to APP_URL', function () {
    $this->submitDeniedForm(UpdateRealmDomainForm::class, ['domain' => 'elsewhere.test'], ['realm' => Realm::master()->slug])->assertForbidden();
    $this->callDeniedAction(CheckRealmDomainAction::class, [], ['realm' => Realm::master()->slug])->assertForbidden();
});
