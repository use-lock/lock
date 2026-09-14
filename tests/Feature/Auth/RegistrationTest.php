<?php
declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;

use function Tests\Helpers\realmRoute;

test('new users can register', function () {
    $realm = Realm::factory()->create();
    $response = $this->post(realmRoute($realm, 'identity.register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user, 'identity');
    $this->assertGuest('web');
    expect($user->name)->toBe('Test User')
        ->and($user->realm_id)->toBe($realm->id);
    $response->assertRedirect(realmRoute($realm, 'account'));
});
