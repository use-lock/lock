<?php
declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Auth\Actions\CreateRealmUser;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Auth\Ui\Actions\BlockUserAction;
use App\Auth\Ui\Actions\DeleteUserAction;
use App\Auth\Ui\Actions\EndAllUserSessionsAction;
use App\Auth\Ui\Actions\EndUserSessionAction;
use App\Auth\Ui\Actions\ResetUserMfaAction;
use App\Auth\Ui\Actions\UnblockUserAction;
use App\Auth\Ui\Forms\CreateUserForm;
use App\Auth\Ui\Forms\UpdateUserEmailForm;
use App\Auth\Ui\Forms\UpdateUserNameForm;
use App\Auth\Ui\Forms\UpdateUserVerificationForm;
use App\Auth\Ui\Tables\UserSessionsTable;
use App\Auth\Ui\Tables\UsersTable;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Lock\Server\Authentication\Models\PasswordResetToken;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Sessions\BackChannel\SendBackChannelLogout;
use Lock\Server\Sessions\Models\OidcSession;
use Lock\Server\Sessions\Models\SessionParticipant;
use Lock\Server\Tokens\Models\AccessToken;

use function Tests\Helpers\createPasskey;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;
use function Tests\Helpers\realmClient;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['slug' => 'acme']);
    $this->other = Realm::factory()->create(['slug' => 'globex']);
});

function activeSessionFor(User $user, ?Client $client = null): OidcSession
{
    $session = OidcSession::factory()->forUser($user)->create(['realm' => $user->realm->slug]);

    if ($client instanceof Client) {
        SessionParticipant::factory()->inSession($session)->forClient($client)->create();
    }

    return $session;
}

function backChannelClientFor(Realm $realm): Client
{
    $client = realmClient($realm, 'RP');
    $client->forceFill(['backchannel_logout_uri' => 'https://rp.test/logout'])->save();

    return $client;
}

/**
 * @param  array<string, mixed>  $query
 * @return array<int, mixed>
 */
function userTableIds(mixed $test, array $query = []): array
{
    $rows = $test->loadTable(UsersTable::class, $query, ['realm' => 'acme'])->assertOk()->json('data');

    return collect(is_array($rows) ? $rows : [])->pluck('id')->all();
}

test('a users-manage admin creates a user and invites them to set a password', function () {
    Notification::fake();

    $this->actingAs(globalAdmin())->get('/admin/realms/acme/users/create')->assertOk();
    $this->submitForm(CreateUserForm::class, [
        'name' => 'Wanda Needle',
        'email' => 'wanda@example.com',
        'credentials' => 'invite',
        'email_verified' => false,
    ], ['realm' => 'acme'])->assertRedirect();

    $user = $this->realm->users()->where('email', 'wanda@example.com')->sole();

    Notification::assertSentTo($user, ResetPassword::class);
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(data_get(AdminEvent::global()->where('type', UserAdminEvent::UserCreated->type())->sole()->context, 'invited'))->toBeTrue();
});

test('a user can be created with an initial password and a verified address', function () {
    Notification::fake();

    $this->actingAs(globalAdmin())->submitForm(CreateUserForm::class, [
        'name' => 'Wanda Needle',
        'email' => 'wanda@example.com',
        'credentials' => 'password',
        'password' => 'correct-horse-battery-staple',
        'email_verified' => true,
    ], ['realm' => 'acme'])->assertRedirect();

    $user = $this->realm->users()->where('email', 'wanda@example.com')->sole();

    Notification::assertNothingSent();
    expect(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('an initial password follows the target realm\'s policy, not the admin\'s own', function () {
    $this->realm->writeSettings(['password_min_length' => 12]);
    $admin = globalAdmin();
    $payload = fn (string $email, string $password): array => [
        'name' => 'Wanda Needle', 'email' => $email, 'credentials' => 'password', 'password' => $password,
    ];

    $this->actingAs($admin)->submitForm(CreateUserForm::class, $payload('wanda@example.com', 'tencharspw'), ['realm' => 'acme'])
        ->assertInvalid(['password']);
    $this->actingAs($admin)->submitForm(CreateUserForm::class, $payload('wanda@example.com', 'tencharspw'), ['realm' => Realm::master()->slug])
        ->assertRedirect();
    $this->actingAs($admin)->submitForm(CreateUserForm::class, $payload('wanda@example.com', 'twelvechars12'), ['realm' => 'acme'])
        ->assertRedirect();

    expect($this->realm->users()->where('email', 'wanda@example.com')->exists())->toBeTrue()
        ->and(Realm::master()->users()->where('email', 'wanda@example.com')->exists())->toBeTrue();
});

test('an address is unique within the realm but free in another realm', function () {
    User::factory()->for($this->realm)->create(['email' => 'taken@example.com']);
    User::factory()->for($this->other)->create(['email' => 'elsewhere@example.com']);
    $admin = globalAdmin();

    $this->actingAs($admin)->submitForm(CreateUserForm::class, [
        'name' => 'Dup', 'email' => 'taken@example.com', 'credentials' => 'invite',
    ], ['realm' => 'acme'])->assertInvalid(['email']);

    $this->actingAs($admin)->submitForm(CreateUserForm::class, [
        'name' => 'Same address, other realm', 'email' => 'elsewhere@example.com', 'credentials' => 'invite',
    ], ['realm' => 'acme'])->assertRedirect();

    expect($this->realm->users()->where('email', 'elsewhere@example.com')->exists())->toBeTrue();
});

test('creating a user is forbidden without users-manage', function () {
    $this->actingAs(globalAdminWith(ManagementScope::UsersRead));

    $this->get('/admin/realms/acme/users/create')->assertForbidden();
    $this->submitDeniedForm(CreateUserForm::class, ['name' => 'X', 'email' => 'x@example.com', 'credentials' => 'invite'], ['realm' => 'acme'])
        ->assertForbidden();
});

test('each profile row saves on its own and records only what it changed', function () {
    $target = User::factory()->for($this->realm)->unverified()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $context = ['realm' => 'acme', 'user' => $target->id];

    $this->actingAs(globalAdmin());
    $this->submitForm(UpdateUserNameForm::class, ['name' => 'New Name'], $context)->assertRedirect();
    $this->submitForm(UpdateUserEmailForm::class, ['email' => 'new@example.com'], $context)->assertRedirect();
    $this->submitForm(UpdateUserVerificationForm::class, ['email_verified' => true], $context)->assertRedirect();

    $target->refresh();
    $changes = AdminEvent::global()->where('type', UserAdminEvent::UserUpdated->type())->get()
        ->map(fn (AdminEvent $event): mixed => data_get($event->context, 'changes'));

    expect($target->name)->toBe('New Name')
        ->and($target->email)->toBe('new@example.com')
        ->and($target->hasVerifiedEmail())->toBeTrue()
        ->and($changes->all())->toEqualCanonicalizing([
            ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
            ['email' => ['old' => 'old@example.com', 'new' => 'new@example.com']],
            ['verified' => ['old' => false, 'new' => true]],
        ]);
});

test('a row rejects an address already used inside the realm', function () {
    $target = User::factory()->for($this->realm)->create();
    $taken = User::factory()->for($this->realm)->create();

    $this->actingAs(globalAdmin())
        ->submitForm(UpdateUserEmailForm::class, ['email' => $taken->email], ['realm' => 'acme', 'user' => $target->id])
        ->assertInvalid(['email']);

    expect($target->refresh()->email)->not->toBe($taken->email);
});

test('editing a user is forbidden without users-manage', function () {
    $target = User::factory()->for($this->realm)->create();

    $this->actingAs(globalAdminWith(ManagementScope::UsersRead))
        ->submitDeniedForm(UpdateUserNameForm::class, ['name' => 'X'], ['realm' => 'acme', 'user' => $target->id])
        ->assertForbidden();
});

test('blocking ends the sessions, notifies the relying parties and unblocking lifts it', function () {
    Bus::fake([SendBackChannelLogout::class]);
    $target = User::factory()->for($this->realm)->create();
    $client = backChannelClientFor($this->realm);
    $session = activeSessionFor($target, $client);
    config()->set('session.driver', 'database');
    DB::table('sessions')->insert(['id' => 'browser-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs(globalAdmin())->callAction(BlockUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertOk();

    expect($target->refresh()->isBlocked())->toBeTrue()
        ->and($session->refresh()->revoked_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(AdminEvent::global()->where('type', UserAdminEvent::UserBlocked->type())->exists())->toBeTrue();
    Bus::assertDispatched(SendBackChannelLogout::class);

    $this->callAction(UnblockUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertOk();

    expect($target->refresh()->isBlocked())->toBeFalse()
        ->and(AdminEvent::global()->where('type', UserAdminEvent::UserUnblocked->type())->exists())->toBeTrue();
});

test('an admin cannot block themselves and one without users-manage cannot block anyone', function () {
    $admin = globalAdmin();
    $target = User::factory()->for($this->realm)->create();

    $this->actingAs($admin)->callDeniedAction(BlockUserAction::class, [], ['realm' => Realm::master()->slug, 'user' => $admin->id])->assertForbidden();
    $this->actingAs(globalAdminWith(ManagementScope::UsersRead))->callDeniedAction(BlockUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertForbidden();

    expect($admin->refresh()->isBlocked())->toBeFalse()
        ->and($target->refresh()->isBlocked())->toBeFalse();
});

test('blocking an already blocked user is rejected', function () {
    $target = User::factory()->for($this->realm)->create(['blocked_at' => now()]);

    $this->actingAs(globalAdmin())->callAction(BlockUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertStatus(422);
});

test('resetting MFA removes authenticator apps, recovery codes and passkeys', function () {
    $target = User::factory()->for($this->realm)->withTwoFactor()->create();
    createPasskey($target);

    $this->actingAs(globalAdmin())->callAction(ResetUserMfaAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertOk();

    expect($target->totpFactors()->exists())->toBeFalse()
        ->and($target->recoveryCodes()->exists())->toBeFalse()
        ->and($target->passkeys()->exists())->toBeFalse()
        ->and($target->refresh()->hasSecondFactor())->toBeFalse()
        ->and(AdminEvent::global()->where('type', UserAdminEvent::UserMfaReset->type())->exists())->toBeTrue();
});

test('resetting MFA is forbidden without users-manage', function () {
    $target = User::factory()->for($this->realm)->withTwoFactor()->create();

    $this->actingAs(globalAdminWith(ManagementScope::UsersRead))
        ->callDeniedAction(ResetUserMfaAction::class, [], ['realm' => 'acme', 'user' => $target->id])
        ->assertForbidden();

    expect($target->totpFactors()->exists())->toBeTrue();
});

test('deleting a user tells relying parties, then removes the user with what was issued to them', function () {
    Bus::fake([SendBackChannelLogout::class]);
    $target = User::factory()->for($this->realm)->create();
    $role = Role::factory()->for($this->realm)->create();
    $target->roles()->attach($role);
    createPasskey($target);
    $client = backChannelClientFor($this->realm);
    activeSessionFor($target, $client);
    $this->realm->runAsCurrent(fn () => $this->issueTokenFor($target, $client));

    $this->actingAs(globalAdmin())->callAction(DeleteUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertOk();

    expect(User::query()->whereKey($target->id)->exists())->toBeFalse()
        ->and(AccessToken::query()->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(DB::table('passkeys')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(DB::table('role_user')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and(data_get(AdminEvent::global()->where('type', UserAdminEvent::UserDeleted->type())->sole()->context, 'email'))->toBe($target->email);
    Bus::assertDispatched(SendBackChannelLogout::class);
});

test('an admin cannot delete themselves and one without users-manage cannot delete anyone', function () {
    $admin = globalAdmin();
    $target = User::factory()->for($this->realm)->create();

    $this->actingAs($admin)->callDeniedAction(DeleteUserAction::class, [], ['realm' => Realm::master()->slug, 'user' => $admin->id])->assertForbidden();
    $this->actingAs(globalAdminWith(ManagementScope::UsersRead))->callDeniedAction(DeleteUserAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($target->id)->exists())->toBeTrue();
});

test('the sessions table lists the user\'s sessions under their own realm only', function () {
    $target = User::factory()->for($this->realm)->create();
    $session = activeSessionFor($target, backChannelClientFor($this->realm));
    activeSessionFor(User::factory()->for($this->realm)->create());

    $this->actingAs(globalAdmin());
    $rows = $this->loadTable(UserSessionsTable::class, context: ['realm' => 'acme', 'user' => $target->id])->assertOk()->json('data');
    $row = collect(is_array($rows) ? $rows : [])->sole();

    expect($row['id'])->toBe($session->id)
        ->and($row['status'])->toBe('active')
        ->and($row['participants_count'])->toBe(1);

    $this->loadDeniedTable(UserSessionsTable::class, context: ['realm' => 'globex', 'user' => $target->id])->assertForbidden();
});

test('a single session can be ended and the relying party is told', function () {
    Bus::fake([SendBackChannelLogout::class]);
    $target = User::factory()->for($this->realm)->create();
    $client = backChannelClientFor($this->realm);
    $ended = activeSessionFor($target, $client);
    $kept = activeSessionFor($target);

    $this->actingAs(globalAdmin())
        ->callAction(EndUserSessionAction::class, [], ['realm' => 'acme', 'user' => $target->id, 'sid' => $ended->id])
        ->assertOk();

    expect($ended->refresh()->revoked_at)->not->toBeNull()
        ->and($kept->refresh()->revoked_at)->toBeNull()
        ->and(data_get(AdminEvent::global()->where('type', UserAdminEvent::UserSessionEnded->type())->sole()->context, 'sid'))->toBe($ended->id);
    Bus::assertDispatched(SendBackChannelLogout::class, fn (SendBackChannelLogout $job): bool => $job->participantId === SessionParticipant::query()->where('session_id', $ended->id)->sole()->id && $job->realm === $this->realm->slug);

    $this->callAction(EndUserSessionAction::class, [], ['realm' => 'acme', 'user' => $target->id, 'sid' => $ended->id])->assertStatus(422);
});

test('ending all sessions revokes every active one', function () {
    $target = User::factory()->for($this->realm)->create();
    $first = activeSessionFor($target);
    $second = activeSessionFor($target);
    $someoneElse = activeSessionFor(User::factory()->for($this->realm)->create());

    $this->actingAs(globalAdmin())->callAction(EndAllUserSessionsAction::class, [], ['realm' => 'acme', 'user' => $target->id])->assertOk();

    expect($first->refresh()->revoked_at)->not->toBeNull()
        ->and($second->refresh()->revoked_at)->not->toBeNull()
        ->and($someoneElse->refresh()->revoked_at)->toBeNull()
        ->and(data_get(AdminEvent::global()->where('type', UserAdminEvent::UserSessionsEnded->type())->sole()->context, 'count'))->toBe(2);
});

test('ending sessions is forbidden without users-manage', function () {
    $target = User::factory()->for($this->realm)->create();
    $session = activeSessionFor($target);

    $this->actingAs(globalAdminWith(ManagementScope::UsersRead))
        ->callDeniedAction(EndAllUserSessionsAction::class, [], ['realm' => 'acme', 'user' => $target->id])
        ->assertForbidden();

    expect($session->refresh()->revoked_at)->toBeNull();
});

test('the users table filters by verification, block state and MFA', function () {
    $verified = User::factory()->for($this->realm)->create();
    $unverified = User::factory()->for($this->realm)->unverified()->create();
    $blocked = User::factory()->for($this->realm)->create(['blocked_at' => now()]);
    $withTotp = User::factory()->for($this->realm)->withTwoFactor()->create();
    $withPasskey = User::factory()->for($this->realm)->create();
    createPasskey($withPasskey);

    $this->actingAs(globalAdmin());

    expect(userTableIds($this, ['tf' => ['verified' => ['value' => 'false']]]))->toBe([$unverified->id])
        ->and(userTableIds($this, ['tf' => ['blocked' => ['value' => 'true']]]))->toBe([$blocked->id])
        ->and(userTableIds($this, ['tf' => ['mfa' => ['value' => 'true']]]))->toEqualCanonicalizing([$withTotp->id, $withPasskey->id])
        ->and(userTableIds($this, ['tf' => ['mfa' => ['value' => 'false']]]))->toEqualCanonicalizing([$verified->id, $unverified->id, $blocked->id]);

    $rows = $this->loadTable(UsersTable::class, [], ['realm' => 'acme'])->assertOk()->json('data');
    $row = collect(is_array($rows) ? $rows : [])->firstWhere('id', $withPasskey->id);

    expect(data_get($row, 'mfa'))->toBeTrue()
        ->and(data_get($row, 'email_verified'))->toBeTrue()
        ->and(data_get($row, 'blocked'))->toBeFalse();
});

test('an invitation is sent with its realm token only after the enclosing transaction commits', function () {
    $messages = [];
    Event::listen(MessageSent::class, function (MessageSent $event) use (&$messages): void {
        $messages[] = (string) $event->message->getHtmlBody();
    });

    $user = DB::transaction(function () use (&$messages): User {
        $user = app(CreateRealmUser::class)->handle($this->realm, 'Invited', 'invite@example.com', null, false);

        expect($messages)->toBeEmpty()
            ->and(PasswordResetToken::query()->where('user_id', $user->id)->exists())->toBeFalse();

        return $user;
    });

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain($this->realm->origin().'/auth/reset-password/')
        ->and(PasswordResetToken::query()->where('user_id', $user->id)->sole()->realm)->toBe($this->realm->slug)
        ->and(Realm::current()->isMaster())->toBeTrue();
});
