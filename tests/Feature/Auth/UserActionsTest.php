<?php

declare(strict_types=1);
use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Auth\Ui\Actions\ResendUserVerification;
use App\Auth\Ui\Actions\SendUserPasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;

test('a users-manage admin can resend verification for an unverified user', function () {
    Notification::fake();
    $admin = globalAdmin();
    $target = User::factory()->unverified()->create();

    $this->actingAs($admin)->callAction(ResendUserVerification::class, [], ['user' => $target->id])->assertOk();

    Notification::assertSentTo($target, VerifyEmail::class);
    expect(AdminEvent::global()->sole()->type)->toBe(UserAdminEvent::VerificationResent->type());
});

test('resending verification for an already-verified user is rejected', function () {
    Notification::fake();
    $admin = globalAdmin();
    $target = User::factory()->create();

    $this->actingAs($admin)->callAction(ResendUserVerification::class, [], ['user' => $target->id])
        ->assertStatus(422);

    Notification::assertNothingSent();
    expect(AdminEvent::global()->count())->toBe(0);
});

test('a users-manage admin can send a password reset', function () {
    Notification::fake();
    $admin = globalAdmin();
    $target = User::factory()->create();

    $this->actingAs($admin)->callAction(SendUserPasswordReset::class, [], ['user' => $target->id])->assertOk();

    Notification::assertSentTo($target, ResetPassword::class);
    expect(AdminEvent::global()->sole()->type)->toBe(UserAdminEvent::PasswordResetSent->type());
});

test('an admin without users-manage cannot resend verification', function () {
    Notification::fake();
    $outsider = globalAdminWith(ManagementScope::UsersRead);
    $target = User::factory()->unverified()->create();

    $this->actingAs($outsider)
        ->callDeniedAction(ResendUserVerification::class, [], ['user' => $target->id])
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('an admin without users-manage cannot send a password reset', function () {
    Notification::fake();
    $outsider = globalAdminWith(ManagementScope::UsersRead);
    $target = User::factory()->create();

    $this->actingAs($outsider)
        ->callDeniedAction(SendUserPasswordReset::class, [], ['user' => $target->id])
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('a throttled password reset does not report or record another delivery', function () {
    Notification::fake();
    $target = User::factory()->create();
    $this->actingAs(globalAdmin());

    $this->callAction(SendUserPasswordReset::class, [], ['user' => $target->id])->assertOk();
    $this->callAction(SendUserPasswordReset::class, [], ['user' => $target->id])->assertStatus(422);

    Notification::assertSentToTimes($target, ResetPassword::class, 1);
    expect(AdminEvent::global()->where('type', UserAdminEvent::PasswordResetSent->type())->count())->toBe(1);
});
