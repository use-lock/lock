<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Shared\Audit\Events\AdminActionPerformed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Lock\Server\Authentication\Models\PasswordResetToken;
use Lock\Server\Clients\Models\Client;

use function Tests\Helpers\realmRoute;

beforeEach(function () {

    config()->set([
        'lock.console_client.id' => 'lock-console',
        'lock.console_client.secret' => 'console-secret',
        'lock.admin.email' => 'root@lock.test',
        'lock.admin.name' => 'Root',
        'lock.admin.password' => 'CorrectHorse42',
    ]);

    $this->tokenRequest = fn (string $secret) => $this->post(realmRoute('admin', 'oidc.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => 'lock-console',
        'client_secret' => $secret,
    ]);
});

it('provisions the console client with the configured id and secret', function () {
    $this->artisan('app:bootstrap')->assertSuccessful();

    $client = Client::query()->where('client_id', 'lock-console')->sole();
    $settings = Realm::master()->clients();

    expect($client->grant_types)->toEqualCanonicalizing(['authorization_code', 'refresh_token', 'client_credentials'])
        ->and($client->realm)->toBe('admin')
        ->and($client->redirect_uris)->toContain(config('app.url').'/login/callback')
        ->and($settings->firstPartyClientId)->toBe('lock-console')
        ->and($settings->firstPartyTrusted)->toBeTrue();

    ($this->tokenRequest)('console-secret')->assertOk()->assertJsonStructure(['access_token']);
});

it('applies a client secret changed in the environment on the next run', function () {
    $this->artisan('app:bootstrap')->assertSuccessful();

    config()->set(['lock.console_client.secret' => 'rotated-secret']);

    $this->artisan('app:bootstrap')->assertSuccessful();

    expect(Client::query()->where('client_id', 'lock-console')->count())->toBe(1);

    ($this->tokenRequest)('rotated-secret')->assertOk();
    ($this->tokenRequest)('console-secret')->assertUnauthorized();
});

it('leaves the console client untouched when no secret is configured', function () {
    $this->artisan('app:bootstrap')->assertSuccessful();

    config()->set(['lock.console_client.secret' => null]);
    Realm::master()->writeSettings(['first_party_trusted' => false]);

    $this->artisan('app:bootstrap')->expectsOutputToContain('skipped')->assertSuccessful();

    ($this->tokenRequest)('console-secret')->assertOk();
    expect(Realm::master()->clients()->firstPartyTrusted)->toBeFalse();
});

it('creates the administrator verified and holding Super Admin', function () {
    $this->artisan('app:bootstrap')->assertSuccessful();

    $admin = Realm::master()->users()->where('email', 'root@lock.test')->sole();

    expect($admin->name)->toBe('Root')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('CorrectHorse42', $admin->password))->toBeTrue()
        ->and($admin->can(ManagementScope::RealmsWrite))->toBeTrue();
});

it('reconciles an existing user into the configured administrator', function () {
    $admin = User::factory()->for(Realm::master())->unverified()->create([
        'email' => 'root@lock.test',
        'password' => 'PreviousSecret9',
    ]);

    $this->artisan('app:bootstrap')->assertSuccessful();

    $admin->refresh();

    expect(Hash::check('CorrectHorse42', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->can(ManagementScope::RealmsWrite))->toBeTrue()
        ->and(Realm::master()->users()->count())->toBe(1);
});

it('leaves an existing password alone when none is configured', function () {
    $admin = User::factory()->for(Realm::master())->unverified()->create([
        'email' => 'root@lock.test',
        'password' => 'PreviousSecret9',
    ]);

    config()->set(['lock.admin.password' => null]);

    $this->artisan('app:bootstrap')->assertSuccessful();

    $admin->refresh();

    expect(Hash::check('PreviousSecret9', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->can(ManagementScope::RealmsWrite))->toBeTrue();
});

it('fails loudly when the configured password violates the realm policy', function () {
    Realm::master()->writeSettings(['password_min_length' => 20]);

    $this->artisan('app:bootstrap')
        ->expectsOutputToContain('The LOCK_ADMIN_PASSWORD field must be at least 20 characters.')
        ->assertFailed();

    expect(Realm::master()->users()->where('email', 'root@lock.test')->exists())->toBeFalse()
        ->and(Client::query()->where('client_id', 'lock-console')->exists())->toBeFalse();
});

it('reports every item as unchanged when run twice', function () {
    $this->artisan('app:bootstrap')->assertSuccessful();

    $this->artisan('app:bootstrap')
        ->expectsOutputToContain('unchanged')
        ->assertSuccessful();

    expect(Client::query()->count())->toBe(1)
        ->and(Realm::master()->users()->count())->toBe(1);

    ($this->tokenRequest)('console-secret')->assertOk();
});

it('sends no invitation when a later administrator audit write rolls back bootstrap', function () {
    Notification::fake();
    config(['lock.admin.password' => null]);
    Event::listen(AdminActionPerformed::class, function (AdminActionPerformed $event): void {
        if ($event->type === UserAdminEvent::SuperAdminGranted) {
            throw new RuntimeException('Audit unavailable');
        }
    });

    $this->artisan('app:bootstrap')->assertFailed();

    Notification::assertNothingSent();
    expect(User::query()->where('email', 'root@lock.test')->exists())->toBeFalse()
        ->and(PasswordResetToken::query()->exists())->toBeFalse();
});
