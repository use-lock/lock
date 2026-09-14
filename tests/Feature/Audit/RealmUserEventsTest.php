<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Audit\Enums\UserEventType;
use App\Audit\Models\UserEvent;
use App\Audit\Ui\Fragments\RealmUserEventContextFragment;
use App\Audit\Ui\Tables\RealmUserEventsTable;
use App\Realms\Models\Realm;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->other = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex']);
});

test('the user events table lists only the selected realm', function () {
    $mine = UserEvent::factory()->for($this->realm)->create();
    UserEvent::factory()->for($this->other)->create();

    $this->actingAs(globalAdmin());
    $this->get('/admin/realms/acme/user-events')->assertOk();

    $rows = $this->loadTable(RealmUserEventsTable::class, context: ['realm' => 'acme'])->assertOk()->json('data');

    expect(collect(is_array($rows) ? $rows : [])->pluck('id')->all())->toBe([$mine->id]);
});

test('a row detail renders the request provenance and the event payload', function () {
    $event = UserEvent::factory()->for($this->realm)->ofType(UserEventType::LoginFailed)->create([
        'ip' => '203.0.113.7',
        'client_id' => 'console',
        'context' => ['reason' => 'invalid_credentials'],
    ]);

    $rendered = $this->actingAs(globalAdmin())
        ->loadFragment(RealmUserEventContextFragment::class, ['realm' => 'acme', 'userEvent' => $event->id])
        ->assertOk()
        ->json();

    expect(json_encode($rendered))->toContain('203.0.113.7')
        ->and(json_encode($rendered))->toContain('console')
        ->and(json_encode($rendered))->toContain('invalid_credentials');
});

test('a detail from another realm is not readable through the fragment', function () {
    $foreign = UserEvent::factory()->for($this->other)->create(['ip' => '198.51.100.9']);

    $rendered = $this->actingAs(globalAdmin())
        ->loadFragment(RealmUserEventContextFragment::class, ['realm' => 'acme', 'userEvent' => $foreign->id])
        ->assertOk()
        ->json();

    expect(json_encode($rendered))->not->toContain('198.51.100.9')
        ->toContain(__('audit.events.detail.empty'));
});

test('the user events console is forbidden without the scope', function () {
    $this->actingAs(globalAdminWith(ManagementScope::UsersRead));

    $this->get('/admin/realms/acme/user-events')->assertForbidden();
    $this->loadDeniedTable(RealmUserEventsTable::class, context: ['realm' => 'acme'])->assertForbidden();
});
