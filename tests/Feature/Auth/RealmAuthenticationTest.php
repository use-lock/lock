<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

use function Tests\Helpers\realmRoute;
use function Tests\Helpers\runningTestCase;

/**
 * @return TestResponse<Response>
 */
function signInToRealm(User $user): TestResponse
{
    auth()->forgetGuards();
    runningTestCase()->flushSession();

    return runningTestCase()->post(
        realmRoute($user->realm, 'identity.login.store'),
        ['email' => $user->email, 'password' => 'password'],
    );
}

it('returns 404 on the password routes of a realm that does not accept the method', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $user = User::factory()->for($realm)->create();
    $realm->writeSettings(['login_methods' => ['passkey']]);

    signInToRealm($user)->assertNotFound();
    $this->get(realmRoute($realm, 'identity.register'))->assertNotFound();

    $this->assertGuest('identity');

    signInToRealm(User::factory()->for(Realm::master())->create())->assertRedirect(realmRoute('admin', 'account'));
});

it('sends a user without a second factor to enrollment when the realm always requires one', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $user = User::factory()->for($realm)->create();

    signInToRealm($user)->assertRedirect(realmRoute($realm, 'account'));

    $realm->writeSettings(['mfa_requirement' => 'always']);

    signInToRealm($user)->assertRedirect(realmRoute($realm, 'identity.two-factor.setup'));
});

it('holds an unverified address at the verification prompt when the realm requires verification', function () {
    $realm = Realm::factory()->create(['slug' => 'partners']);
    $user = User::factory()->for($realm)->unverified()->create();

    signInToRealm($user)->assertRedirect(realmRoute($realm, 'account'));

    $realm->writeSettings(['email_verification_required' => true]);

    signInToRealm($user)->assertRedirect(realmRoute($realm, 'identity.verification.notice'));
});
