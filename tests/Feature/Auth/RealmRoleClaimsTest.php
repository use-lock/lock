<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Shared\Consents\ConsentPrompt;
use Lock\Server\Shared\Consents\ConsentView;

use function Tests\Helpers\jwtClaims;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    app()->bind(ConsentView::class, fn () => new class implements ConsentView
    {
        public function respond(ConsentPrompt $prompt, Request $request): JsonResponse
        {
            return response()->json(['authToken' => $prompt->authToken]);
        }
    });

    $this->user = User::factory()->for(Realm::master())->create();
    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/callback']);
});

it('carries the sorted realm roles in the id token, the access token and userinfo', function () {
    $this->user->roles()->attach(Role::factory()->for(Realm::master())->create(['name' => 'member']));
    $this->user->roles()->attach(Role::factory()->for(Realm::master())->create(['name' => 'admin']));
    Role::factory()->for(Realm::master())->create(['name' => 'unassigned']);

    $tokens = $this->authorizeAndApprove($this->user, $this->client, 'openid profile');
    $tokens->response->assertOk();

    expect(jwtClaims($tokens->idToken)['roles'])->toBe(['admin', 'member'])
        ->and(jwtClaims($tokens->accessToken)['roles'])->toBe(['admin', 'member']);

    $this->getJson(route('oidc.userinfo'), ['Authorization' => "Bearer {$tokens->accessToken}"])
        ->assertOk()
        ->assertJsonPath('roles', ['admin', 'member']);
});

it('carries an empty roles list for a user without realm roles', function () {
    $tokens = $this->authorizeAndApprove($this->user, $this->client, 'openid');
    $tokens->response->assertOk();

    expect(jwtClaims($tokens->idToken)['roles'])->toBe([])
        ->and(jwtClaims($tokens->accessToken)['roles'])->toBe([]);
});

it('reflects a role change on the refreshed access token', function () {
    $tokens = $this->authorizeAndApprove($this->user, $this->client, 'openid');
    $tokens->response->assertOk();

    $this->user->roles()->attach(Role::factory()->for(Realm::master())->create(['name' => 'admin']));

    $refreshed = $this->post(route('oidc.token'), [
        'grant_type' => 'refresh_token',
        'client_id' => $this->client->client_id,
        'client_secret' => $this->client->secret,
        'refresh_token' => (string) $tokens->refreshToken,
    ])->assertOk();

    expect(jwtClaims($refreshed->json('access_token'))['roles'])->toBe(['admin'])
        ->and(jwtClaims($refreshed->json('id_token'))['roles'])->toBe(['admin']);
});
