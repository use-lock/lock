<?php

declare(strict_types=1);

use App\Audit\Enums\UserEventType;
use App\Audit\Models\UserEvent;
use App\Auth\Models\User;
use App\Auth\Ui\Forms\EmailForm;
use App\Auth\Ui\Forms\NameForm;
use App\Auth\Ui\Forms\PasswordForm;
use App\Realms\Models\Realm;
use Illuminate\Support\Facades\Hash;

use function Tests\Helpers\realmRoute;

test('users can update their name', function () {
    $user = User::factory()->for(Realm::master())->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->submitForm(NameForm::class, ['name' => 'New Name'])
        ->assertRedirect(realmRoute($user->realm, 'account'));

    expect($user->refresh()->name)->toBe('New Name');
});

test('changing the email saves it and resets the verification status', function () {
    $user = User::factory()->for(Realm::master())->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->submitForm(EmailForm::class, ['email' => 'new@example.com'])
        ->assertRedirect(realmRoute($user->realm, 'account'));

    expect($user->refresh()->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('an address is only taken within the user own realm', function () {
    $user = User::factory()->for(Realm::master())->create();
    User::factory()->for($user->realm)->create(['email' => 'taken@example.com']);
    User::factory()->create(['email' => 'elsewhere@example.com']);

    $this->actingAs($user)
        ->submitForm(EmailForm::class, ['email' => 'taken@example.com'])
        ->assertInvalid(['email']);

    $this->actingAs($user)
        ->submitForm(EmailForm::class, ['email' => 'elsewhere@example.com'])
        ->assertRedirect(realmRoute($user->realm, 'account'));

    expect($user->refresh()->email)->toBe('elsewhere@example.com');
});

test('users can update their password', function () {
    $user = User::factory()->for(Realm::master())->create();

    $this->actingAs($user)
        ->submitForm(PasswordForm::class, [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
});

test('a password change enforces the current password, the policy, and the confirmation', function (array $overrides, string $errorField) {
    $user = User::factory()->for(Realm::master())->create();
    $originalHash = $user->password;

    $this->actingAs($user)
        ->submitForm(PasswordForm::class, [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            ...$overrides,
        ])
        ->assertSessionHasErrors($errorField);

    expect($user->refresh()->password)->toBe($originalHash);
})->with([
    'wrong current password' => [['current_password' => 'not-the-password'], 'current_password'],
    'weak new password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'different-password'], 'password'],
]);

test('updating with an unchanged email keeps the verification status', function () {
    $user = User::factory()->for(Realm::master())->create([
        'email' => 'same@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->submitForm(EmailForm::class, ['email' => 'same@example.com'])
        ->assertRedirect();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('a changed password honours the realm password history and is recorded', function () {
    $user = User::factory()->for(Realm::master())->create();
    $user->realm->writeSettings(['password_history' => 2]);
    $change = fn (string $password) => $this->actingAs($user)->submitForm(PasswordForm::class, [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $change('password')->assertInvalid(['password']);
    $change('another-secure-password')->assertSessionHasNoErrors();

    expect(Hash::check('another-secure-password', $user->refresh()->password))->toBeTrue()
        ->and(UserEvent::query()->where('type', UserEventType::PasswordChanged->value)->where('user_id', $user->id)->exists())->toBeTrue();
});
