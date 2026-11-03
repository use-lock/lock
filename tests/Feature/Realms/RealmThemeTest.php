<?php

declare(strict_types=1);

namespace Tests\Feature\Realms;

use App\Audit\Models\AdminEvent;
use App\Realms\Actions\CreateRealm;
use App\Realms\Data\CreateRealmData;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmThemeConfiguration;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\fullThemeValues;
use function Tests\Helpers\realmRoute;
use function Tests\Helpers\themeRealm;

beforeEach(function () {
    domainReachesThisInstance();

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
    $this->otherRealm = Realm::factory()->create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.localhost']);
});

function themeSignIn(TestCase $test, Realm $realm): string
{
    return (string) $test->get(realmRoute($realm, 'identity.login'))->assertOk()->getContent();
}

it('leaves a realm without a theme as it was', function () {
    $page = themeSignIn($this, $this->realm);

    expect($page)
        ->not->toContain('data-realm-theme')
        ->not->toContain('lock-auth-card')
        ->toContain('<meta name="application-name" content="'.e((string) config('app.name')).'">');
});

it('renders a realm\'s theme on its own host and on no other', function () {
    themeRealm($this->realm, ['values' => fullThemeValues()]);

    expect(themeSignIn($this, $this->realm))
        ->toContain('data-realm-theme')
        ->toContain('--lt-primary: #3366cc;')
        ->toContain('--lock-button-primary-fill: linear-gradient(180deg, #4477dd 0, #3366cc 50%, #2255aa 100%);')
        ->toContain('--lock-background-image: url("https://cdn.example.com/art/key.webp");')
        ->toContain('lock-auth-card')
        ->and(themeSignIn($this, $this->otherRealm))->not->toContain('data-realm-theme')->not->toContain('#3366cc');
});

it('accepts every setting the registry declares a shape for', function () {
    themeRealm($this->realm, ['values' => fullThemeValues()]);

    expect(array_diff(array_keys(fullThemeValues()), array_keys(RealmThemeConfiguration::settings())))->toBeEmpty()
        ->and($this->realm->refresh()->theme->values)->toHaveSameSize(fullThemeValues());
});

it('turns the input border style into the underline the stylesheet reads', function () {
    themeRealm($this->realm, ['values' => ['input-border-style' => 'underline', 'input-border-width' => '2px']]);

    expect(themeSignIn($this, $this->realm))
        ->toContain('--lock-input-border-edge: 0 0 var(--lock-input-border-width, 1px) 0;');
});

it('emits a font face for every declared file', function () {
    themeRealm($this->realm, ['font_faces' => [
        ['family' => 'Example Text', 'file' => 'https://cdn.example.com/text.woff2', 'weight' => 700, 'style' => 'italic'],
    ]]);

    expect(themeSignIn($this, $this->realm))
        ->toContain("@font-face { font-family: 'Example Text'; src: url(\"https://cdn.example.com/text.woff2\"); font-weight: 700; font-style: italic; font-display: swap; }");
});

it('names a themed realm after itself and leaves other realms alone', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc']]);

    expect(themeSignIn($this, $this->realm))
        ->toContain('<meta name="application-name" content="Emberfall">')
        ->toContain('<title>Emberfall</title>')
        ->and(themeSignIn($this, $this->otherRealm))->not->toContain('content="Other"');
});

it('fixes the appearance over the visitor\'s choice', function () {
    themeRealm($this->realm, ['appearance' => 'dark']);

    $page = $this->withUnencryptedCookie('appearance', 'light')->get(realmRoute($this->realm, 'identity.login'))->assertOk();

    expect($page->getContent())
        ->toContain('class="dark"')
        ->toContain('data-forced-appearance="dark"')
        ->toContain('color-scheme: dark;');
});

it('keeps the visitor\'s choice while the appearance follows the system', function () {
    themeRealm($this->realm, ['appearance' => 'system', 'values' => ['--lt-primary' => '#3366cc']]);

    $page = $this->withUnencryptedCookie('appearance', 'dark')->get(realmRoute($this->realm, 'identity.login'))->assertOk();

    expect($page->getContent())
        ->toContain('class="dark"')
        ->not->toContain('data-forced-appearance')
        ->not->toContain('data-realm-theme] { color-scheme');
});

it('refuses hostile values', function (string $setting, string $value) {
    expect(fn () => themeRealm($this->realm, ['values' => [$setting => $value]]))
        ->toThrow(ValidationException::class)
        ->and($this->realm->refresh()->theme->values)->toBeEmpty();
})->with([
    'closing the style element' => ['--lt-primary', '</style><script>alert(1)</script>'],
    'breaking out of a declaration' => ['--lt-primary', '#fff; } body { display: none'],
    'an image request in a colour' => ['--lt-bg', 'url(https://evil.example/x)'],
    'an image request in a gradient' => ['button-primary-fill', 'linear-gradient(url(https://evil.example/x), #000)'],
    'an image request in a shadow' => ['button-primary-shadow', '0 0 0 url(https://evil.example/x)'],
    'an import in a font stack' => ['--lt-font-sans', "'x'; @import url(https://evil.example/x)"],
    'a foreign variable in a font' => ['label-font', '700 12px/1 var(--evil)'],
    'a filter that loads a document' => ['button-hover-filter', 'url(https://evil.example/x#f)'],
    'a quote in a file URL' => ['logo-url', 'https://cdn.example.com/a.png"); } body { display: none; } a { b: url("x'],
    'a plain http file' => ['logo-url', 'http://cdn.example.com/a.png'],
    'a script URL' => ['logo-url', 'javascript:alert(1)'],
    'a data URL' => ['background-image-url', 'data:image/svg+xml,<svg onload=alert(1)>'],
    'a traversing asset' => ['logo-url', 'asset:../../secret.png'],
    'a length that is a word' => ['--lt-radius', 'large'],
    'an unknown keyword' => ['label-transform', 'expression(alert(1))'],
    'an address that is none' => ['mail-from-address', 'not an address'],
]);

it('refuses a setting the registry does not know', function () {
    themeRealm($this->realm, ['values' => ['--lt-sidebar-w' => '10px']]);
})->throws(ValidationException::class);

it('refuses an appearance it does not know', function () {
    themeRealm($this->realm, ['appearance' => 'sepia']);
})->throws(ValidationException::class);

it('refuses a hostile font face', function (array $face) {
    themeRealm($this->realm, ['font_faces' => [['family' => 'Example', 'file' => 'https://cdn.example.com/a.woff2', ...$face]]]);
})->throws(ValidationException::class)->with([
    'a family breaking out' => [['family' => "x'; } body { display: none; } a { b: 'c"]],
    'a file breaking out' => [['file' => 'https://cdn.example.com/a.woff2"); } body { display: none']],
    'a weight out of range' => [['weight' => 5000]],
    'an unknown style' => [['style' => 'oblique; color: red']],
]);

it('gives the administration realm no theme', function () {
    themeRealm(Realm::master(), ['values' => ['--lt-primary' => '#3366cc']]);
})->throws(ValidationException::class);

it('forgets a value set to null and keeps the others', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc', '--lt-bg' => '#101418']]);
    themeRealm($this->realm->refresh(), ['values' => ['--lt-primary' => null]]);

    expect($this->realm->refresh()->theme->values)->toBe(['--lt-bg' => '#101418']);
});

it('records what changed and nothing when nothing did', function () {
    themeRealm($this->realm, ['appearance' => 'dark', 'values' => ['--lt-primary' => '#3366cc']]);

    $events = fn () => AdminEvent::query()->where('type', RealmAdminEvent::RealmUpdated->value)->get();

    expect($events())->toHaveCount(1)
        ->and($events()->sole()->context)->toBe(['changes' => [
            'theme.appearance' => ['old' => 'system', 'new' => 'dark'],
            'theme.--lt-primary' => ['old' => null, 'new' => '#3366cc'],
        ]]);

    themeRealm($this->realm->refresh(), ['appearance' => 'dark', 'values' => ['--lt-primary' => '#3366cc']]);

    expect($events())->toHaveCount(1);
});

it('creates a realm with a theme and refuses one with a hostile theme', function () {
    $realm = app(CreateRealm::class)->handle(CreateRealmData::from([
        'name' => 'Globex',
        'slug' => 'globex',
        'domain' => 'globex.localhost',
        'theme' => ['appearance' => 'dark', 'values' => ['--lt-primary' => '#3366cc']],
    ]));

    expect($realm->refresh()->fixedAppearance())->toBe('dark')
        ->and($realm->theme->value('--lt-primary'))->toBe('#3366cc')
        ->and(fn () => app(CreateRealm::class)->handle(CreateRealmData::from([
            'name' => 'Initech',
            'slug' => 'initech',
            'domain' => 'initech.localhost',
            'theme' => ['values' => ['--lt-primary' => '</style>']],
        ])))->toThrow(ValidationException::class)
        ->and(Realm::query()->where('slug', 'initech')->exists())->toBeFalse();
});
