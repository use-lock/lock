<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;

it('returns app-specific claims from userinfo', function () {
    $user = User::factory()->for(Realm::master())->create(['locale' => 'de', 'timezone' => 'Europe/Berlin']);
    $token = $this->issueTokenFor($user, scopes: ['openid', 'profile', 'email']);

    $this->withToken($token)->getJson(route('oidc.userinfo'))
        ->assertOk()
        ->assertJsonPath('sub', (string) $user->id)
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('locale', 'de')
        ->assertJsonPath('zoneinfo', 'Europe/Berlin');
});
