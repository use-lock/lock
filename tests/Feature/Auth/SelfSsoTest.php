<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use RuntimeException;

use function Tests\Helpers\selfSsoProviderFake;

/*
 * The claim-by-claim validation matrix lives in the package's IdTokenValidator
 * tests; these cover the app acting as provider and relying party at once.
 */

/**
 * @param  TestResponse<Response>  $response
 */
function redirectTarget(TestResponse $response): string
{
    $location = $response->headers->get('Location');

    if ($location === null) {
        throw new RuntimeException('The response carries no Location header.');
    }

    return $location;
}

it('establishes the web session only after the self-sso callback', function () {
    Exceptions::fake();

    $user = User::factory()->for(Realm::master())->create();
    $fake = selfSsoProviderFake();

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest('identity');
    $this->assertGuest('web');

    $login = $this->get(route('login'))->assertRedirect();
    $fake->assertRedirectedToProvider($login);

    // The real login redirect seeded state/nonce/verifier; mint the id_token
    // against the live nonce so the callback validates end to end.
    $nonce = session('oidc-client.nonce');
    expect($nonce)->toBeString()->not->toBeEmpty();
    $fake->loginAs($user, ['nonce' => $nonce]);

    $this->get(redirectTarget($login))->assertRedirect(route('identity.login'));
    $this->assertGuest('identity');
    $this->assertGuest('web');

    $identityLogin = $this->post(route('identity.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user, 'identity');
    $this->assertGuest('web');

    $authorization = $this->get(redirectTarget($identityLogin))->assertRedirect();
    expect(redirectTarget($authorization))->toStartWith(config('app.url').'/login/callback?');
    $this->assertAuthenticatedAs($user, 'identity');
    $this->assertGuest('web');

    $this->get(redirectTarget($authorization))->assertRedirect('/');
    $this->assertAuthenticatedAs($user, 'identity');
    $this->assertAuthenticatedAs($user, 'web');
    $fake->assertCodeExchanged();
    Exceptions::assertNothingReported();
});

it('rejects a failed token exchange without creating a web session', function () {
    $fake = selfSsoProviderFake()->failTokenExchange();

    $this->withSession($fake->callbackContext())
        ->get($fake->callbackUrl())
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oidc');

    $this->assertGuest('web');
    $fake->assertCodeExchanged();
});
