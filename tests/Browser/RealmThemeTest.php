<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\fullThemeValues;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\keepTheMasterRealmOnItsOwnHost;
use function Tests\Helpers\themeRealm;
use function Tests\Helpers\visitAccountAs;

const BROWSER_THEME_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const BROWSER_THEME_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 100"><rect width="400" height="100" fill="#3366cc"/></svg>';

beforeEach(function () {
    domainReachesThisInstance();
    keepTheMasterRealmOnItsOwnHost();
    Storage::fake(RealmThemeAsset::DISK);

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
});

function visitRealmSignIn(Realm $realm): PendingAwaitablePage
{
    Playwright::setHost($realm->host());

    return visit('/auth/login');
}

function computedStyle(string $selector, string $property): string
{
    return "getComputedStyle(document.querySelector('{$selector}')).{$property}";
}

it('dresses the sign-in page in a full theme', function () {
    $store = app(StoreRealmThemeAsset::class);
    $store->handle($this->realm, 'wordmark.svg', BROWSER_THEME_SVG);
    $store->handle($this->realm, 'art.png', (string) base64_decode(BROWSER_THEME_PNG, true));

    themeRealm($this->realm, ['appearance' => 'dark', 'values' => [...fullThemeValues(), 'background-image-url' => 'asset:art.png']]);

    visitRealmSignIn($this->realm->refresh())
        ->assertTitleContains('Emberfall')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertScript("document.querySelector('.lock-auth-logo').naturalWidth > 0")
        ->assertScript(computedStyle('.lock-auth-panel', 'maxWidth'), '460px')
        ->assertScript(computedStyle('.lock-auth-shell', 'backgroundImage').".includes('art.png')")
        ->assertScript(computedStyle('[data-slot=button].bg-lt-primary', 'backgroundImage').".startsWith('linear-gradient')")
        ->assertScript(computedStyle('[data-slot=input]', 'borderTopWidth'), '0px')
        ->assertScript(computedStyle('[data-slot=input]', 'borderBottomWidth'), '2px')
        ->assertScript(computedStyle('[data-slot=label]', 'textTransform'), 'uppercase')
        ->assertNoJavaScriptErrors();
});

it('keeps a fixed mode over what the browser stored', function () {
    themeRealm($this->realm, ['appearance' => 'dark']);

    $page = visitRealmSignIn($this->realm->refresh());

    $page->script("localStorage.setItem('appearance', 'light'); document.cookie = 'appearance=light; path=/'");

    $page->refresh()
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertNoJavaScriptErrors();
});

it('leaves what a theme does not set as Lattice declares it', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc']]);

    visitRealmSignIn($this->realm->refresh())
        ->assertScript(computedStyle('[data-slot=button].bg-lt-primary', 'backgroundColor'), 'rgb(51, 102, 204)')
        ->assertScript(computedStyle('[data-slot=label]', 'textTransform'), 'none')
        ->assertScript(computedStyle('[data-slot=label]', 'letterSpacing'), 'normal')
        ->assertScript(computedStyle('[data-slot=input]', 'borderTopWidth'), '1px')
        ->assertScript(computedStyle('[data-slot=heading]', 'textShadow'), 'none')
        ->assertMissing('.lock-auth-panel')
        ->assertNoJavaScriptErrors();
});

it('leaves a realm without a theme untouched', function () {
    visitRealmSignIn($this->realm)
        ->assertMissing('.lock-auth-card')
        ->assertScript("document.documentElement.hasAttribute('data-realm-theme')", false)
        ->assertScript(computedStyle('[data-slot=button].bg-lt-primary', 'backgroundImage'), 'none')
        ->assertNoJavaScriptErrors();
});

it('fits a phone without scrolling sideways', function () {
    themeRealm($this->realm, ['appearance' => 'dark', 'values' => fullThemeValues()]);

    Playwright::setHost($this->realm->host());

    visit('/auth/login')->on()->mobile()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoJavaScriptErrors();
});

it('names the realm on the account page and hides the mode a realm fixes', function () {
    themeRealm($this->realm, ['appearance' => 'dark']);

    visitAccountAs(User::factory()->for($this->realm->refresh())->create())
        ->assertSee('Emberfall')
        ->assertMissing('[data-test="row-appearance"]')
        ->assertScript("document.documentElement.classList.contains('dark')")
        ->assertNoJavaScriptErrors();
});

it('saves a setting and uploads a file from the design tab', function () {
    $this->actingAs(globalAdmin());

    // Text on purpose: the browser server's multipart parser mangles binary bytes.
    $file = tempnam(sys_get_temp_dir(), 'theme').'.svg';
    file_put_contents($file, BROWSER_THEME_SVG);

    visit('/admin/realms/emberfall/settings?tabs=design')
        ->assertSee('emberfall.localhost')
        ->click('[data-test="theme-lt-primary"] button')
        ->fill('[data-test="theme-lt-primary"] input[name="value"]', '#3366cc')
        ->click('[data-test="theme-lt-primary"] [data-test="form-submit"]')
        ->assertSee('Design saved.')
        ->assertSee('#3366cc')
        ->attach('input[type="file"]', $file)
        ->fill('input[name="name"]', 'logo.svg')
        ->click('[data-lattice-form="admin.realms.theme-asset"]')
        ->assertSee('File uploaded.')
        ->assertSee('logo.svg')
        ->assertNoJavaScriptErrors();

    File::delete($file);

    expect($this->realm->refresh()->theme->value('--lt-primary'))->toBe('#3366cc')
        ->and($this->realm->themeAssets()->pluck('name')->all())->toBe(['logo.svg']);
});
