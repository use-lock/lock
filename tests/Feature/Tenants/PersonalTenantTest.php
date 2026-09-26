<?php
declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Enums\RoleKind;
use App\Roles\Models\Role;
use App\Tenants\Actions\CreateTenant;
use Tests\TestCase;

use function Tests\Helpers\realmRoute;

function registerIn(TestCase $test, Realm $realm, string $email = 'ada@example.com'): User
{
    $test->post(realmRoute($realm, 'identity.register.store'), [
        'name' => 'Ada Lovelace',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    return User::where('email', $email)->firstOrFail();
}

function tenancyRealm(bool $tenantOnRegistration = true): Realm
{
    $realm = Realm::factory()->create();
    $realm->writeSettings(['tenancy' => true, 'tenant_on_registration' => $tenantOnRegistration]);

    return $realm;
}

it('gives a user who registers a personal tenant holding the realm owner presets', function () {
    $realm = tenancyRealm();
    $owner = Role::factory()->for($realm)->create(['name' => 'Owner', 'kind' => RoleKind::TenantOwner]);
    Role::factory()->for($realm)->create(['name' => 'Member', 'kind' => RoleKind::TenantPreset]);

    $user = registerIn($this, $realm);

    $tenant = $user->tenants()->sole();
    expect($tenant->personal)->toBeTrue()
        ->and($tenant->name)->toBe('Ada Lovelace')
        ->and($tenant->slug)->toBe('ada-lovelace')
        ->and($tenant->realm_id)->toBe($realm->id)
        ->and($user->memberships()->sole()->roles->modelKeys())->toBe([$owner->id]);
});

it('keeps tenant slugs unique within the realm', function () {
    $realm = tenancyRealm();
    $createTenant = app(CreateTenant::class);

    $first = $createTenant->handle(User::factory()->for($realm)->create(), 'Acme');
    $second = $createTenant->handle(User::factory()->for($realm)->create(), 'Acme');

    expect($first->slug)->toBe('acme')
        ->and($second->slug)->toStartWith('acme-')->not->toBe('acme');
});

it('refuses to create a tenant in a realm without tenancy', function () {
    app(CreateTenant::class)->handle(User::factory()->for(Realm::factory())->create(), 'Acme');
})->throws(LogicException::class);

it('creates no tenant where the realm does not promise one', function (bool $tenancy, bool $tenantOnRegistration) {
    $realm = Realm::factory()->create();
    $realm->writeSettings(['tenancy' => $tenancy, 'tenant_on_registration' => $tenantOnRegistration]);

    expect(registerIn($this, $realm)->tenants()->exists())->toBeFalse();
})->with([
    'without tenancy' => [false, true],
    'without the registration setting' => [true, false],
]);
