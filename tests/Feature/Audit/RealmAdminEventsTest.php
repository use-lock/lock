<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Audit\Ui\Tables\RealmAdminEventsTable;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\globalAdminWith;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    $this->other = Realm::factory()->create(['name' => 'Globex', 'slug' => 'globex']);
});

test('the realm table lists only that realm and leaves the instance trail out', function () {
    Audit::record(RealmAdminEvent::RealmUpdated, $this->realm, $this->realm);
    Audit::record(RealmAdminEvent::RealmUpdated, $this->other, $this->other);
    Audit::record(UserAdminEvent::UserBlocked, User::factory()->for($this->realm)->create());

    $this->actingAs(globalAdmin());
    $this->get('/admin/realms/acme/admin-events')->assertOk();

    $rows = $this->loadTable(RealmAdminEventsTable::class, context: ['realm' => 'acme'])->assertOk()->json('data');
    $expected = AdminEvent::forRealm($this->realm)->sole()->id;

    expect(collect(is_array($rows) ? $rows : [])->pluck('id')->all())->toBe([$expected]);
});

test('the realm admin events console is forbidden without the scope', function () {
    $this->actingAs(globalAdminWith(ManagementScope::UsersRead));

    $this->get('/admin/realms/acme/admin-events')->assertForbidden();
    $this->loadDeniedTable(RealmAdminEventsTable::class, context: ['realm' => 'acme'])->assertForbidden();
});
