<?php
declare(strict_types=1);

use App\Auth\Models\User;
use App\Auth\Ui\Forms\NameForm;
use App\Auth\Ui\Forms\PasswordForm;
use App\Realms\Models\Realm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Lattice\Core\Services\EndpointAreas;

// The account page mints its components on the account endpoints. The Lattice
// helpers rebind a fresh request before every seal, so the area is set there.
beforeEach(function () {
    $this->app->rebinding('request', fn ($app, Request $request) => $request->attributes->set(EndpointAreas::ATTRIBUTE, 'account'));
});

test('guessing the current password through the account console is cut off after five tries', function () {
    $user = User::factory()->for(Realm::master())->create();
    $change = fn (string $current) => $this->actingAs($user, 'identity')->submitForm(PasswordForm::class, [
        'current_password' => $current,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    foreach (range(1, 5) as $attempt) {
        $change("wrong-password-{$attempt}")->assertSessionHasErrors('current_password');
    }

    $change('password')->assertTooManyRequests();

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();

    $this->actingAs($user, 'identity')
        ->submitForm(NameForm::class, ['name' => 'Still Allowed'])
        ->assertRedirect();
});
