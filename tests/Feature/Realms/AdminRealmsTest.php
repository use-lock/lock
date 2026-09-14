<?php

declare(strict_types=1);

use App\Admin\ManagementRoles;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Realms\Ui\Forms\CreateRealmForm;
use App\Realms\Ui\Forms\DeleteRealmForm;
use App\Realms\Ui\Forms\RealmSettingForm;
use App\Realms\Ui\Forms\UpdateRealmForm;
use App\Realms\Ui\Tables\RealmsTable;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\realmSetting;
use function Tests\Helpers\renderedTabKeys;

test('every admin can list and open realms', function (string $roleName) {
    $realm = Realm::factory()->create();

    $this->actingAs(globalAdmin($roleName));
    $this->loadTable(RealmsTable::class)->assertOk();
    $this->get('/admin/realms')->assertOk();
    $this->get("/admin/realms/{$realm->slug}/settings")->assertOk();
})->with(['support admin' => ManagementRoles::SUPPORT, 'super admin' => ManagementRoles::SUPER_ADMIN]);

test('a realm user reaches neither the realms list nor a realm', function () {
    $realm = Realm::factory()->create();

    $this->actingAs(User::factory()->create());
    $this->loadDeniedTable(RealmsTable::class)->assertForbidden();
    $this->get('/admin/realms')->assertForbidden();
    $this->get("/admin/realms/{$realm->slug}/settings")->assertForbidden();
});

test('realm readers can inspect providers while editing tabs require write access', function () {
    $realm = Realm::factory()->create();

    $support = $this->actingAs(globalAdmin(ManagementRoles::SUPPORT))->get("/admin/realms/{$realm->slug}/settings")->assertOk();
    $superAdmin = $this->actingAs(globalAdmin())->get("/admin/realms/{$realm->slug}/settings")->assertOk();

    expect(renderedTabKeys($support))->toBe(['general', 'social'])
        ->and(renderedTabKeys($superAdmin))->toBe(['general', 'login', 'social', 'tokens', 'sessions', 'mfa', 'passwords', 'clients']);
});

test('a realm row counts the realm\'s users', function () {
    $realm = Realm::factory()->create();
    User::factory()->for($realm)->count(2)->create();

    $table = $this->actingAs(globalAdmin())->loadTable(RealmsTable::class)->assertOk();

    expect($table->row($realm->id)->value('users_count'))->toBe(2);
});

test('a support admin cannot create, edit or delete a realm', function () {
    $realm = Realm::factory()->create();

    $this->actingAs(globalAdmin(ManagementRoles::SUPPORT));
    $this->get('/admin/realms/create')->assertForbidden();
    $this->submitDeniedForm(CreateRealmForm::class, ['name' => 'Forbidden', 'slug' => 'forbidden', 'domain' => 'forbidden.example.com'])->assertForbidden();
    $this->submitDeniedForm(UpdateRealmForm::class, ['name' => 'Renamed'], ['realm' => $realm->slug])->assertForbidden();
    $this->submitDeniedForm(RealmSettingForm::class, ...realmSetting($realm, 'access_token_lifetime', 300))->assertForbidden();
    $this->submitDeniedForm(DeleteRealmForm::class, ['name' => $realm->name], ['realm' => $realm->slug])->assertForbidden();

    expect(Realm::query()->where('slug', 'forbidden')->exists())->toBeFalse()
        ->and($realm->refresh()->name)->not->toBe('Renamed')
        ->and($realm->tokens()->accessTokenLifetime)->toBe(900);
});
