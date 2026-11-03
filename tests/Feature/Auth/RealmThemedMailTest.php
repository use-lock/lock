<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Models\User;
use App\Auth\Notifications\RealmResetPassword;
use App\Auth\Notifications\RealmVerifyEmail;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use Illuminate\Support\Facades\Storage;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\themeRealm;

beforeEach(function () {
    domainReachesThisInstance();
    Storage::fake(RealmThemeAsset::DISK);

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
    $this->user = User::factory()->for($this->realm)->create(['email' => 'player@example.com']);
});

it('sends a realm without a theme the default mail', function () {
    $mail = new RealmResetPassword('token')->toMail($this->user);

    expect($mail->markdown)->toBe('notifications::email')
        ->and($mail->from)->toBeEmpty();
});

it('carries a themed realm\'s look, name and sender in a reset mail', function () {
    themeRealm($this->realm, ['values' => [
        '--lt-primary' => '#3366cc',
        '--lt-primary-fg' => '#ffffff',
        '--lt-bg' => '#101418',
        'mail-from-name' => 'Emberfall Support',
        'mail-from-address' => 'hello@emberfall.example',
    ]]);

    $mail = new RealmResetPassword('secret-token')->toMail($this->user->refresh());
    $html = (string) $mail->render();

    expect($mail->from)->toBe(['hello@emberfall.example', 'Emberfall Support'])
        ->and($html)->toContain('#3366cc')
        ->and($html)->toContain('#101418')
        ->and($html)->toContain('Emberfall')
        ->and($html)->toContain('secret-token')
        ->and($html)->not->toContain((string) config('app.name').'</');
});

it('falls back to the realm\'s name and the instance address as the sender', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc']]);

    config(['mail.from.address' => 'instance@example.com']);

    expect(new RealmVerifyEmail()->toMail($this->user->refresh())->from)->toBe(['instance@example.com', 'Emberfall']);
});

it('themes a verification mail with its signed link', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc']]);

    $html = (string) new RealmVerifyEmail()->toMail($this->user->refresh())->render();

    expect($html)->toContain('#3366cc')->and($html)->toContain('signature=');
});

it('follows the recipient\'s realm when the console sends it', function () {
    themeRealm($this->realm, ['values' => ['--lt-primary' => '#3366cc']]);

    expect(Realm::current()->isMaster())->toBeTrue()
        ->and((string) new RealmResetPassword('token')->toMail($this->user->refresh())->render())->toContain('#3366cc');
});

it('shows a raster logo by its absolute address and the name for anything else', function () {
    app(StoreRealmThemeAsset::class)->handle($this->realm, 'mail.png', "\x89PNG\r\n\x1a\nimage");
    app(StoreRealmThemeAsset::class)->handle($this->realm, 'mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    themeRealm($this->realm, ['values' => ['mail-logo-url' => 'asset:mail.png']]);

    expect((string) new RealmResetPassword('token')->toMail($this->user->refresh())->render())
        ->toContain('<img src="'.$this->realm->origin().'/theme-assets/mail.png');

    themeRealm($this->realm->refresh(), ['values' => ['mail-logo-url' => 'asset:mark.svg']]);

    expect((string) new RealmResetPassword('token')->toMail($this->user->refresh())->render())
        ->not->toContain('<img');
});
