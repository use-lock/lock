<?php

declare(strict_types=1);

namespace Tests\Feature\Realms;

use App\Audit\Models\AdminEvent;
use App\Realms\Actions\DeleteRealm;
use App\Realms\Actions\DeleteRealmThemeAsset;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use App\Shared\Audit\Events\AdminActionPerformed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\realmRoute;
use function Tests\Helpers\realmUrl;
use function Tests\Helpers\themeRealm;

const THEME_PNG = "\x89PNG\r\n\x1a\nimage";
const THEME_SVG = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
const THEME_WOFF2 = 'wOF2font';

beforeEach(function () {
    domainReachesThisInstance();
    Storage::fake(RealmThemeAsset::DISK);

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
    $this->store = app(StoreRealmThemeAsset::class);
});

it('serves a file on its realm\'s host', function () {
    $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    $response = $this->get(realmUrl($this->realm, 'theme-assets/logo.png'))->assertOk();

    expect($response->getContent())->toBe(THEME_PNG)
        ->and($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('does not serve one realm\'s file on another realm\'s host', function () {
    $other = Realm::factory()->create(['slug' => 'other', 'domain' => 'other.localhost']);
    $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    $this->get(realmUrl($other, 'theme-assets/logo.png'))->assertNotFound();
    $this->get(realmUrl(Realm::master(), 'theme-assets/logo.png'))->assertNotFound();
});

it('sandboxes an SVG so it cannot run script on the realm\'s origin', function () {
    $this->store->handle($this->realm, 'logo.svg', THEME_SVG);

    $response = $this->get(realmUrl($this->realm, 'theme-assets/logo.svg'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('caches a file for good only under its current version', function () {
    $asset = $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    expect($this->get(realmUrl($this->realm, 'theme-assets/logo.png?v='.$asset->version()))->headers->get('Cache-Control'))->toContain('immutable')
        ->and($this->get(realmUrl($this->realm, 'theme-assets/logo.png?v=stale'))->headers->get('Cache-Control'))->not->toContain('immutable');
});

it('refuses a file that is not what its name says', function (string $name, string $contents) {
    expect(fn () => $this->store->handle($this->realm, $name, $contents))->toThrow(ValidationException::class)
        ->and($this->realm->themeAssets()->count())->toBe(0);
})->with([
    'html as a png' => ['logo.png', '<html><script>alert(1)</script></html>'],
    'html as an svg' => ['logo.svg', '<html><script>alert(1)</script></html>'],
    'a png as a font' => ['font.woff2', THEME_PNG],
    'an empty file' => ['logo.png', ''],
    'a type it does not take' => ['page.html', '<html></html>'],
    'no type at all' => ['logo', THEME_PNG],
    'a traversing name' => ['../logo.png', THEME_PNG],
    'an uppercase name' => ['Logo.png', THEME_PNG],
]);

it('refuses a file over the limit', function () {
    $this->store->handle($this->realm, 'big.png', THEME_PNG.str_repeat('x', RealmThemeAsset::MAX_BYTES));
})->throws(ValidationException::class);

it('gives the administration realm no files', function () {
    $this->store->handle(Realm::master(), 'logo.png', THEME_PNG);
})->throws(ValidationException::class);

it('replaces a file and removes the one it replaced', function () {
    $first = $this->store->handle($this->realm, 'logo.png', THEME_PNG);
    $second = $this->store->handle($this->realm, 'logo.png', THEME_PNG.'-v2');

    expect($this->realm->themeAssets()->count())->toBe(1)
        ->and($second->sha256)->not->toBe($first->sha256);

    Storage::disk(RealmThemeAsset::DISK)->assertMissing($first->path);
    Storage::disk(RealmThemeAsset::DISK)->assertExists($second->path);
});

it('takes the same upload twice without recording a change', function () {
    $this->store->handle($this->realm, 'logo.png', THEME_PNG);
    $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    expect(AdminEvent::query()->where('type', RealmAdminEvent::RealmUpdated->value)->count())->toBe(1);
});

it('keeps two names of the same contents apart', function () {
    $first = $this->store->handle($this->realm, 'one.png', THEME_PNG);
    $second = $this->store->handle($this->realm, 'two.png', THEME_PNG);

    app(DeleteRealmThemeAsset::class)->handle($first);

    Storage::disk(RealmThemeAsset::DISK)->assertExists($second->path);
});

it('leaves no file behind when the record cannot be written', function () {
    Event::listen(AdminActionPerformed::class, function (): void {
        throw new RuntimeException('The audit row could not be written.');
    });

    expect(fn () => $this->store->handle($this->realm, 'logo.png', THEME_PNG))->toThrow(RuntimeException::class)
        ->and($this->realm->themeAssets()->count())->toBe(0)
        ->and(Storage::disk(RealmThemeAsset::DISK)->allFiles())->toBeEmpty();
});

it('removes a deleted file from the disk', function () {
    $asset = $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    app(DeleteRealmThemeAsset::class)->handle($asset);

    Storage::disk(RealmThemeAsset::DISK)->assertMissing($asset->path);
    $this->get(realmUrl($this->realm, 'theme-assets/logo.png'))->assertNotFound();
});

it('removes a realm\'s files with the realm', function () {
    $asset = $this->store->handle($this->realm, 'logo.png', THEME_PNG);

    app(DeleteRealm::class)->handle($this->realm);

    expect(RealmThemeAsset::query()->count())->toBe(0);
    Storage::disk(RealmThemeAsset::DISK)->assertMissing($asset->path);
});

it('points a font face and the background at the hosted file under its version', function () {
    $font = $this->store->handle($this->realm, 'text.woff2', THEME_WOFF2);
    $art = $this->store->handle($this->realm, 'art.png', THEME_PNG);

    themeRealm($this->realm, [
        'values' => ['background-image-url' => 'asset:art.png', 'favicon-url' => 'asset:art.png'],
        'font_faces' => [['family' => 'Example', 'file' => 'asset:text.woff2']],
    ]);

    expect($this->get(realmRoute($this->realm, 'identity.login'))->assertOk()->getContent())
        ->toContain('src: url("/theme-assets/text.woff2?v='.$font->version().'")')
        ->toContain('--lock-background-image: url("/theme-assets/art.png?v='.$art->version().'");')
        ->toContain('<link rel="icon" href="/theme-assets/art.png?v='.$art->version().'">');
});

it('takes a reference to a file that is not there yet and leaves it out', function () {
    themeRealm($this->realm, [
        'values' => ['logo-url' => 'asset:later.png', 'background-image-url' => 'asset:later.png'],
        'font_faces' => [['family' => 'Example', 'file' => 'asset:later.woff2']],
    ]);

    expect($this->realm->refresh()->theme->value('logo-url'))->toBe('asset:later.png')
        ->and($this->get(realmRoute($this->realm, 'identity.login'))->assertOk()->getContent())->not->toContain('later.png')->not->toContain('later.woff2')->not->toContain('@font-face')->not->toContain('--lock-background-image');
});
