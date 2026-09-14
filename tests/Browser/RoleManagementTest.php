<?php

declare(strict_types=1);

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;

use function Tests\Helpers\globalAdmin;

it('creates a role opens its detail from a row and edits it from the row menu', function (bool $mobile) {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $resource = Resource::factory()->for($realm)->create(['identifier' => 'documents', 'name' => 'Documents']);
    $scope = ResourceScope::factory()->for($resource)->create(['value' => 'documents:read']);
    $this->actingAs(globalAdmin());
    $page = visit('/admin/realms/acme/roles');

    if ($mobile) {
        $page->resize(390, 844);
    }

    $page->click('Create role')
        ->assertPathIs('/admin/realms/acme/roles/create')
        ->fill('input[name="name"]', 'editor')
        ->fill('input[name="description"]', 'Document editor')
        ->check('[id="scope_ids-'.$scope->id.'"]')
        ->click('[data-test="form-submit"]')
        ->assertSee('Role created.')
        ->assertSee('documents:read')
        ->assertNoJavaScriptErrors();

    $role = $realm->roles()->where('name', 'editor')->sole();

    $page->assertPathIs("/admin/realms/acme/roles/{$role->id}")
        ->click('Edit')
        ->fill('input[name="description"]', 'Updated from detail')
        ->click('[data-test="action-form-submit"]')
        ->assertSee('Role updated.')
        ->assertSee('Updated from detail')
        ->navigate('/admin/realms/acme/roles')
        ->click('editor')
        ->assertPathIs("/admin/realms/acme/roles/{$role->id}")
        ->navigate('/admin/realms/acme/roles')
        ->click('button[aria-label="More actions"]')
        ->click('Edit')
        ->fill('input[name="name"]', 'reviewer')
        ->click('[data-test="action-form-submit"]')
        ->assertSee('Role updated.')
        ->assertSee('reviewer')
        ->click('reviewer')
        ->assertPathIs("/admin/realms/acme/roles/{$role->id}")
        ->assertSee('reviewer')
        ->assertSee('documents:read')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->screenshot(filename: $mobile ? 'role-detail-mobile' : 'role-detail-desktop')
        ->assertNoJavaScriptErrors();

    expect($role->refresh()->name)->toBe('reviewer')
        ->and($role->description)->toBe('Updated from detail')
        ->and($role->scopes()->pluck('resource_scopes.id')->all())->toBe([$scope->id]);

    $page->click('button[aria-label="More actions"]')
        ->click('Delete')
        ->click('[data-test="confirm-accept"]')
        ->assertPathIs('/admin/realms/acme/roles')
        ->assertSee('Role deleted.')
        ->assertNoJavaScriptErrors();

    expect($realm->roles()->whereKey($role->id)->exists())->toBeFalse();
})->with(['desktop' => false, 'mobile' => true]);
