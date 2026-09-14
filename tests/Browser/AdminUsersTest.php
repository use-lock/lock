<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Lock\Server\Sessions\Models\OidcSession;

use function Tests\Helpers\globalAdmin;

beforeEach(function () {
    $this->target = User::factory()->for(Realm::factory()->create(['slug' => 'acme']))->create(['name' => 'Wanda Needle', 'email' => 'wanda@acme.test']);
    $this->actingAs(globalAdmin());
});

it('blocks and unblocks a user through the changing row menu', function () {
    $page = visit('/admin/realms/acme/users')
        ->click('button[aria-label="More actions"]')
        ->click('Block user')
        ->click('[data-test="confirm-accept"]')
        ->assertSee('User blocked.')
        ->assertNoJavaScriptErrors();

    expect($this->target->refresh()->isBlocked())->toBeTrue();

    $page->click('button[aria-label="More actions"]')
        ->click('Unblock user')
        ->click('[data-test="confirm-accept"]')
        ->assertSee('User unblocked.')
        ->assertNoJavaScriptErrors();

    expect($this->target->refresh()->isBlocked())->toBeFalse();
});

it('ends an active session through the session table menu', function () {
    $session = OidcSession::factory()->forUser($this->target)->create(['realm' => 'acme']);

    visit("/admin/realms/acme/users/{$this->target->id}")
        ->click('[data-test="admin.users.sessions.row-actions"]')
        ->click('End')
        ->click('[data-test="confirm-accept"]')
        ->assertSee('Session ended.')
        ->assertNoJavaScriptErrors();

    expect($session->refresh()->revoked_at)->not->toBeNull();
});
