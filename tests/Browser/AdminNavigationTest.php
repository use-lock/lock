<?php

declare(strict_types=1);

use App\Realms\Models\Realm;

use function Tests\Helpers\globalAdmin;

it('remembers the selected realm across instance pages and subsequent navigation', function () {
    Realm::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
    Realm::factory()->create(['slug' => 'globex', 'name' => 'Globex']);
    $this->actingAs(globalAdmin());

    visit('/admin/realms')
        ->click('[data-test="realm-switcher"]')
        ->click('[role="menu"] a[href$="/admin/realms/acme/users"]')
        ->assertPathIs('/admin/realms/acme/users')
        ->click('a[href$="/admin/api"]')
        ->assertPathIs('/admin/api')
        ->refresh()
        ->assertSeeIn('[data-test="realm-switcher"]', 'Acme')
        ->click('[data-test="admin-sidebar-menu"] a[href$="/admin/realms/acme/users"]')
        ->assertPathIs('/admin/realms/acme/users')
        ->click('[data-test="realm-switcher"]')
        ->click('[role="menu"] a[href$="/admin/realms/globex/users"]')
        ->assertPathIs('/admin/realms/globex/users')
        ->click('a[href$="/admin/api"]')
        ->assertPathIs('/admin/api')
        ->refresh()
        ->assertSeeIn('[data-test="realm-switcher"]', 'Globex')
        ->click('[data-test="admin-sidebar-menu"] a[href$="/admin/realms/globex/users"]')
        ->assertPathIs('/admin/realms/globex/users')
        ->assertNoJavaScriptErrors();
});
