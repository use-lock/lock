<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Models\User;
use App\Auth\Ui\Actions\SignOutBrowserSession;
use App\Auth\Ui\Tables\BrowserSessionsTable;
use App\Realms\Models\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lock\Server\Sessions\Models\OidcSession;

function browserSessionRow(User $user, string $id, string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->getKey(),
        'ip_address' => '203.0.113.7',
        'user_agent' => $userAgent,
        'payload' => '',
        'last_activity' => now()->subMinutes(5)->getTimestamp(),
    ]);
}

function currentSessionId(): string
{
    return Str::random(40);
}

/**
 * @return array<int, array<string, mixed>>
 */
function browserSessionRows(mixed $test, User $user, string $current): array
{
    return $test->actingAs($user)
        ->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->loadTable(BrowserSessionsTable::class)
        ->assertOk()
        ->json('data');
}

it('lists the user browser sessions and marks the current one', function () {
    $user = User::factory()->create();
    $current = currentSessionId();
    browserSessionRow($user, $current);
    browserSessionRow($user, 'other-session', userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1');
    browserSessionRow(User::factory()->create(), 'someone-elses-session');

    $rows = collect(browserSessionRows($this, $user, $current));

    expect($rows->pluck('status', 'id')->all())->toBe([$current => 'current', 'other-session' => 'active'])
        ->and($rows->pluck('device', 'id')->all())->toBe([$current => 'Chrome · macOS', 'other-session' => 'Safari · iPhone']);
});

it('signs out another browser session and revokes the provider session behind it', function () {
    $user = User::factory()->for(Realm::master())->create();
    $oidcSession = OidcSession::factory()->forUser($user)->create(['browser_session_id' => 'other-session']);
    browserSessionRow($user, 'other-session');

    $this->actingAs($user)
        ->withCredentials()
        ->withCookie(config('session.cookie'), currentSessionId())
        ->callAction(SignOutBrowserSession::class, context: ['session' => 'other-session'])
        ->assertOk();

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse()
        ->and(OidcSession::query()->whereKey($oidcSession->id)->firstOrFail()->revoked_at)->not->toBeNull();
});

it('refuses to sign out the current session or another user session', function () {
    $user = User::factory()->create();
    $current = currentSessionId();
    browserSessionRow($user, $current);
    browserSessionRow(User::factory()->create(), 'someone-elses-session');

    $this->actingAs($user)
        ->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->callAction(SignOutBrowserSession::class, context: ['session' => $current])
        ->assertForbidden();

    $this->actingAs($user)
        ->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->callAction(SignOutBrowserSession::class, context: ['session' => 'someone-elses-session'])
        ->assertStatus(422);

    expect(DB::table('sessions')->count())->toBe(2);
});
