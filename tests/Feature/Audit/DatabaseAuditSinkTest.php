<?php

declare(strict_types=1);

use App\Audit\Enums\UserEventCategory;
use App\Audit\Enums\UserEventType;
use App\Audit\Models\UserEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Lock\Server\Authentication\Events\LoginFailed;
use Lock\Server\Authentication\Events\LoginSucceeded;

test('a security event raised inside a realm is recorded against that realm', function () {
    $realm = Realm::factory()->create();
    $user = User::factory()->for($realm)->create();

    $realm->runAsCurrent(fn () => event(new LoginSucceeded($user->id, ['pwd'])));

    $event = UserEvent::query()->sole();

    expect($event->type)->toBe(UserEventType::LoginSucceeded->value)
        ->and($event->category)->toBe(UserEventCategory::Auth->value)
        ->and($event->realm_id)->toBe($realm->id)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->failure)->toBeFalse()
        ->and($event->context)->toBe(['amr' => ['pwd']]);
});

test('a failed sign-in is recorded with the address that was tried and no user', function () {
    $realm = Realm::factory()->create();

    $realm->runAsCurrent(fn () => event(new LoginFailed(
        method: 'password',
        reason: 'invalid_credentials',
        username: 'ghost@example.com',
    )));

    $event = UserEvent::query()->sole();

    expect($event->failure)->toBeTrue()
        ->and($event->user_id)->toBeNull()
        ->and($event->actor_name)->toBe('ghost@example.com')
        ->and(data_get($event->context, 'reason'))->toBe('invalid_credentials');
});
