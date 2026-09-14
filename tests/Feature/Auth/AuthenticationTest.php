<?php
declare(strict_types=1);

use App\Admin\ManagementRoles;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Support\Facades\Http;

use function Tests\Helpers\consoleSessionFor;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\identitySessionFor;
use function Tests\Helpers\realmRoute;

test('the root path sends a master realm user to their account and an admin to the realms console', function () {

    $this->get('/')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->for(Realm::master())->create())->get('/')->assertRedirect(realmRoute(Realm::master(), 'account'));
    $this->actingAs(globalAdmin(ManagementRoles::SUPPORT))->get('/')->assertRedirect(route('admin.realms'));
});

test('logging out ends both the console and the identity session', function () {
    $user = User::factory()->for(Realm::master())->create();
    Http::fake(['*' => Http::response([], 503)]);

    $this->withSession(identitySessionFor($user) + consoleSessionFor($user))
        ->get(realmRoute(Realm::master(), 'account'))
        ->assertOk();

    $this->post(route('logout'))->assertRedirect();
    auth()->forgetGuards();

    $this->get('/')->assertRedirect(route('login'));
    $this->get(realmRoute(Realm::master(), 'account'))->assertRedirect(realmRoute(Realm::master(), 'identity.login'));
});
