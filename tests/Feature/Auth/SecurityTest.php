<?php
declare(strict_types=1);

use App\Auth\Models\User;

use function Tests\Helpers\createPasskey;
use function Tests\Helpers\realmRoute;

test('the two-factor section stays hidden until the password is confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user, 'identity')
        ->get(realmRoute($user->realm, 'account'))
        ->assertOk()
        ->assertSee(__('user.security.confirm.action'))
        ->assertDontSee(__('user.security.two-factor.add-method'));

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(realmRoute($user->realm, 'account'))
        ->assertOk()
        ->assertSee(__('user.security.two-factor.add-method'))
        ->assertSee(__('user.security.two-factor.status.enabled'));
});

test('a passkey alone counts as a second factor', function () {
    $user = User::factory()->create();
    createPasskey($user, 'MacBook Pro');

    $this->actingAs($user, 'identity')
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(realmRoute($user->realm, 'account'))
        ->assertOk()
        ->assertSee(__('user.security.two-factor.status.enabled'));
});
