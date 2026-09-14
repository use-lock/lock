<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Models\User;
use App\Auth\Support\RealmUserProvider;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Lock\Server\Brokering\Models\SocialAccount;
use Lock\Server\Brokering\SocialAccountManager;
use Lock\Server\Brokering\SocialProviderRegistry;
use Lock\Server\Shared\Brokering\CreateUserFromSocialAccount;
use Lock\Server\Shared\Brokering\SocialAuthenticationException;
use Lock\Server\Shared\Brokering\SocialUser;

use function Tests\Helpers\realmRoute;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->realm->writeSettings(['login_methods' => ['password', 'social']]);
});

/**
 * The provider the identity guard runs on, which is what scopes the lookup
 * behind `link_by_verified_email` to the current realm.
 */
function realmSocialIdentityUsers(): UserProvider
{
    return new RealmUserProvider(app('hash'), User::class);
}

function realmSocialUser(bool $emailVerified = true, ?string $email = 'jane@acme.test'): SocialUser
{
    return new SocialUser(
        id: 'upstream-1',
        email: $email,
        emailVerified: $emailVerified,
        name: 'Jane Doe',
        nickname: 'jane',
        avatar: null,
    );
}

test('a realm brokers only its own enabled providers', function () {
    RealmSocialProvider::factory()->for($this->realm)->create(['key' => 'google']);
    RealmSocialProvider::factory()->for($this->realm)->disabled()->create(['key' => 'github']);
    RealmSocialProvider::factory()->for(Realm::master())->create(['key' => 'elsewhere']);

    $enabled = $this->realm->runAsCurrent(fn (): array => array_keys(app(SocialProviderRegistry::class)->enabled()));

    expect($enabled)->toBe(['google']);
});

test('a disabled provider stops answering its sign-in URL', function () {
    $provider = RealmSocialProvider::factory()->for($this->realm)->create(['key' => 'google']);

    $this->get(realmRoute($this->realm, 'identity.social.redirect', ['provider' => 'google']))->assertRedirect();

    $provider->update(['enabled' => false]);

    $this->get(realmRoute($this->realm, 'identity.social.redirect', ['provider' => 'google']))->assertNotFound();
});

test('a provider of another realm is not reachable from this realm', function () {
    RealmSocialProvider::factory()->for(Realm::master())->create(['key' => 'google']);

    $this->get(realmRoute($this->realm, 'identity.social.redirect', ['provider' => 'google']))->assertNotFound();
});

test('the sign-in redirect carries the realm callback URL', function () {
    RealmSocialProvider::factory()->for($this->realm)->create([
        'key' => 'google',
        'config' => ['client_id' => 'client-id', 'client_secret' => 'secret'],
    ]);

    $location = (string) $this->get(realmRoute($this->realm, 'identity.social.redirect', ['provider' => 'google']))
        ->assertRedirect()
        ->headers->get('location');

    expect($location)->toStartWith('https://accounts.google.com/')
        ->and($location)->toContain(urlencode($this->realm->origin().'/auth/social/google/callback'))
        ->and($location)->toContain('client_id=client-id');
});

test('just-in-time provisioning creates a verified user in the current realm', function () {
    User::factory()->for(Realm::master())->create(['email' => 'jane@acme.test']);

    $user = $this->realm->runAsCurrent(fn (): User => app(CreateUserFromSocialAccount::class)(realmSocialUser(), 'google'));

    expect($user->realm_id)->toBe($this->realm->id)
        ->and($user->email)->toBe('jane@acme.test')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('just-in-time provisioning refuses an identity the upstream has not verified', function (?string $email, bool $verified) {
    $provision = fn () => $this->realm->runAsCurrent(fn () => app(CreateUserFromSocialAccount::class)(realmSocialUser($verified, $email), 'google'));

    expect($provision)->toThrow(SocialAuthenticationException::class)
        ->and(User::query()->where('realm_id', $this->realm->id)->exists())->toBeFalse();
})->with([
    'unverified address' => ['jane@acme.test', false],
    'no address' => [null, true],
]);

test('a brokered login attaches to the realm user with the same verified address', function () {
    $existing = User::factory()->for($this->realm)->create(['email' => 'jane@acme.test']);

    $resolved = $this->realm->runAsCurrent(fn (): ?Authenticatable => app(SocialAccountManager::class)
        ->resolveUser('google', realmSocialUser(), realmSocialIdentityUsers()));

    expect($resolved?->getAuthIdentifier())->toBe($existing->id)
        ->and(SocialAccount::query()->where('provider_user_id', 'upstream-1')->sole()->user_id)->toBe($existing->id);
});

test('a realm with both linking and provisioning off turns an unknown identity away', function () {
    $this->realm->writeSettings([
        'link_by_verified_email' => false,
        'auto_provision' => false,
    ]);

    $resolved = Realm::query()->whereKey($this->realm->id)->sole()->runAsCurrent(fn (): ?Authenticatable => app(SocialAccountManager::class)
        ->resolveUser('google', realmSocialUser(), realmSocialIdentityUsers()));

    expect($resolved)->toBeNull()
        ->and(User::query()->where('realm_id', $this->realm->id)->exists())->toBeFalse();
});

test('provisioning refuses an existing local email when automatic linking is disabled', function () {
    $existing = User::factory()->for($this->realm)->create(['email' => 'jane@acme.test']);
    $this->realm->writeSettings(['link_by_verified_email' => false, 'auto_provision' => true]);

    expect(fn () => $this->realm->runAsCurrent(fn () => app(SocialAccountManager::class)
        ->resolveUser('google', realmSocialUser(), realmSocialIdentityUsers())))
        ->toThrow(SocialAuthenticationException::class)
        ->and($this->realm->users()->count())->toBe(1)
        ->and($existing->refresh()->name)->not->toBe('Jane Doe')
        ->and(SocialAccount::query()->exists())->toBeFalse();
});
