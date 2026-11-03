<?php

declare(strict_types=1);

namespace Tests\Feature\Realms;

use App\Admin\ManagementRoles;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use App\Realms\Ui\Actions\DeleteRealmThemeAssetAction;
use App\Realms\Ui\Forms\RealmThemeSettingForm;
use App\Realms\Ui\Forms\UploadRealmThemeAssetForm;
use App\Realms\Ui\Tables\RealmThemeAssetsTable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\renderedTabKeys;
use function Tests\Helpers\themeRealm;

beforeEach(function () {
    domainReachesThisInstance();
    Storage::fake(RealmThemeAsset::DISK);

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
});

it('shows the design tab to writers and never on the administration realm', function () {
    $this->actingAs(globalAdmin());

    expect(renderedTabKeys($this->get('/admin/realms/emberfall/settings')->assertOk()))->toContain('design')
        ->and(renderedTabKeys($this->get('/admin/realms/'.config('lock.master_realm').'/settings')->assertOk()))->not->toContain('design');

    $this->actingAs(globalAdmin(ManagementRoles::SUPPORT));

    expect(renderedTabKeys($this->get('/admin/realms/emberfall/settings')->assertOk()))->not->toContain('design');
});

it('saves a setting and clears it again', function () {
    $this->actingAs(globalAdmin());

    $this->submitForm(RealmThemeSettingForm::class, ['value' => '#3366cc'], ['realm' => 'emberfall', 'field' => '--lt-primary'])->assertRedirect();

    expect($this->realm->refresh()->theme->value('--lt-primary'))->toBe('#3366cc');

    $this->submitForm(RealmThemeSettingForm::class, ['value' => ''], ['realm' => 'emberfall', 'field' => '--lt-primary'])->assertRedirect();

    expect($this->realm->refresh()->theme->value('--lt-primary'))->toBeNull();
});

it('reports a hostile value on the field and stores nothing', function () {
    $this->actingAs(globalAdmin());

    $this->submitForm(RealmThemeSettingForm::class, ['value' => '</style>'], ['realm' => 'emberfall', 'field' => '--lt-primary'])
        ->assertInvalid(['value']);

    expect($this->realm->refresh()->theme->values)->toBeEmpty();
});

it('fixes the appearance', function () {
    $this->actingAs(globalAdmin());

    $this->submitForm(RealmThemeSettingForm::class, ['value' => 'dark'], ['realm' => 'emberfall', 'field' => 'appearance'])->assertRedirect();

    expect($this->realm->refresh()->fixedAppearance())->toBe('dark');
});

it('replaces the font faces', function () {
    $this->actingAs(globalAdmin());

    $this->submitForm(RealmThemeSettingForm::class, ['font_faces' => [
        ['family' => 'Example', 'file' => 'asset:text.woff2', 'weight' => '700', 'style' => 'italic'],
    ]], ['realm' => 'emberfall', 'field' => 'font-faces'])->assertRedirect();

    expect($this->realm->refresh()->theme->fontFaces)->toHaveCount(1)
        ->and($this->realm->theme->fontFaces[0]->weight)->toBe(700);
});

it('refuses a reader every write', function () {
    $this->actingAs(globalAdmin(ManagementRoles::SUPPORT));

    $this->submitDeniedForm(RealmThemeSettingForm::class, ['value' => '#3366cc'], ['realm' => 'emberfall', 'field' => '--lt-primary'])->assertForbidden();
    $this->submitDeniedForm(UploadRealmThemeAssetForm::class, ['name' => 'logo.png'], ['realm' => 'emberfall'])->assertForbidden();
});

it('uploads a file under the name it came with', function () {
    $this->actingAs(globalAdmin());

    $this->submitForm(UploadRealmThemeAssetForm::class, [
        'file' => UploadedFile::fake()->createWithContent('Logo.PNG', "\x89PNG\r\n\x1a\nimage"),
    ], ['realm' => 'emberfall'])->assertRedirect();

    $asset = $this->realm->themeAssets()->sole();

    expect($asset->name)->toBe('logo.png')
        ->and($asset->mime_type)->toBe('image/png');

    Storage::disk(RealmThemeAsset::DISK)->assertExists($asset->path);
});

it('lists a realm\'s files and no other realm\'s', function () {
    $other = Realm::factory()->create(['slug' => 'other', 'domain' => 'other.localhost']);
    $mine = app(StoreRealmThemeAsset::class)->handle($this->realm, 'mine.png', "\x89PNG\r\n\x1a\nmine");
    app(StoreRealmThemeAsset::class)->handle($other, 'theirs.png', "\x89PNG\r\n\x1a\ntheirs");

    $this->actingAs(globalAdmin());

    $table = $this->loadTable(RealmThemeAssetsTable::class, context: ['realm' => 'emberfall'])->assertOk();

    expect($table->row($mine->id)->value('name'))->toBe('mine.png')
        ->and($table->rows())->toHaveCount(1);
});

it('deletes a file through its row and not another realm\'s', function () {
    $other = Realm::factory()->create(['slug' => 'other', 'domain' => 'other.localhost']);
    $mine = app(StoreRealmThemeAsset::class)->handle($this->realm, 'mine.png', "\x89PNG\r\n\x1a\nmine");
    $theirs = app(StoreRealmThemeAsset::class)->handle($other, 'theirs.png', "\x89PNG\r\n\x1a\ntheirs");

    $this->actingAs(globalAdmin());

    $this->callDeniedAction(DeleteRealmThemeAssetAction::class, context: ['realm' => 'emberfall', 'themeAsset' => $theirs->id])->assertForbidden();
    $this->callAction(DeleteRealmThemeAssetAction::class, context: ['realm' => 'emberfall', 'themeAsset' => $mine->id])->assertOk();

    expect(RealmThemeAsset::query()->pluck('name')->all())->toBe(['theirs.png']);
});

it('names references that have no file yet', function () {
    themeRealm($this->realm, ['values' => ['logo-url' => 'asset:later.svg']]);

    $this->actingAs(globalAdmin());

    expect($this->get('/admin/realms/emberfall/settings')->assertOk()->getContent())->toContain('later.svg');
});
