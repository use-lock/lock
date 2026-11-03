<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use Illuminate\Support\Facades\Storage;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\managementApiToken;
use function Tests\Helpers\realmUrl;

const API_THEME_PNG = "\x89PNG\r\n\x1a\nimage";

beforeEach(function () {
    domainReachesThisInstance();
    Storage::fake(RealmThemeAsset::DISK);

    $this->realm = Realm::factory()->create(['name' => 'Emberfall', 'slug' => 'emberfall', 'domain' => 'emberfall.localhost']);
});

function themeApiUrl(string $path = 'emberfall'): string
{
    return realmUrl(Realm::master(), '/api/v1/realms/'.$path);
}

it('reads an unthemed realm with an empty map, not an empty list', function () {
    $response = $this->withToken(managementApiToken($this, ManagementScope::RealmsRead))
        ->getJson(themeApiUrl())
        ->assertOk()
        ->assertJsonPath('data.theme.appearance', 'system')
        ->assertJsonPath('data.theme.font_faces', []);

    expect($response->getContent())->toContain('"values":{}');
});

it('writes a theme and reads it back', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(themeApiUrl(), ['theme' => [
            'appearance' => 'dark',
            'values' => ['--lt-primary' => '#3366cc', 'logo-url' => 'asset:wordmark.svg'],
            'font_faces' => [['family' => 'Example', 'file' => 'asset:text.woff2', 'weight' => 400, 'style' => 'normal']],
        ]])
        ->assertOk()
        ->assertJsonPath('data.theme.appearance', 'dark')
        ->assertJsonPath('data.theme.values.logo-url', 'asset:wordmark.svg')
        ->assertJsonPath('data.theme.font_faces.0.family', 'Example');
});

it('creates a realm with a theme', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->postJson(realmUrl(Realm::master(), '/api/v1/realms'), [
            'name' => 'Globex',
            'slug' => 'globex',
            'domain' => 'globex.localhost',
            'theme' => ['appearance' => 'light'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.theme.appearance', 'light');
});

it('reports a hostile value under its place in the theme', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(themeApiUrl(), ['theme' => ['values' => ['--lt-primary' => '</style>']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['theme.values.--lt-primary']);
});

it('reports a value of the wrong JSON type instead of failing on it', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(themeApiUrl(), ['theme' => ['values' => ['--lt-radius' => 5]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['theme.values.--lt-radius']);
});

it('rejects a key the theme payload does not have', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(themeApiUrl(), ['theme' => ['css' => 'body { display: none }']])
        ->assertUnprocessable();
});

it('refuses a theme on the administration realm', function () {
    $this->withToken(managementApiToken($this, ManagementScope::RealmsWrite))
        ->patchJson(themeApiUrl((string) config('lock.master_realm')), ['theme' => ['appearance' => 'dark']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['theme']);
});

it('refuses a read token a file upload', function () {
    $server = $this->transformHeadersToServerVars(['Authorization' => 'Bearer '.managementApiToken($this, ManagementScope::RealmsRead), 'Accept' => 'application/json']);

    $this->call('PUT', themeApiUrl('emberfall/theme-assets/logo.png'), server: $server, content: API_THEME_PNG)
        ->assertForbidden();
});

it('takes a file, reads it back and keeps the same upload idempotent', function () {
    $token = managementApiToken($this, ManagementScope::RealmsWrite, ManagementScope::RealmsRead);
    $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json', 'Content-Type' => 'image/png'];
    $server = $this->transformHeadersToServerVars($headers);

    $first = $this->call('PUT', themeApiUrl('emberfall/theme-assets/logo.png'), server: $server, content: API_THEME_PNG)
        ->assertOk()
        ->assertJsonPath('data.name', 'logo.png')
        ->assertJsonPath('data.mime_type', 'image/png')
        ->assertJsonPath('data.size', strlen(API_THEME_PNG))
        ->assertJsonPath('data.sha256', hash('sha256', API_THEME_PNG));

    expect($first->json('data.url'))->toStartWith($this->realm->origin().'/theme-assets/logo.png?v=');

    auth()->forgetGuards();

    $this->call('PUT', themeApiUrl('emberfall/theme-assets/logo.png'), server: $server, content: API_THEME_PNG)->assertOk();

    expect($this->realm->themeAssets()->count())->toBe(1);

    auth()->forgetGuards();

    $this->withToken($token)->getJson(themeApiUrl('emberfall/theme-assets/logo.png'))
        ->assertOk()
        ->assertJsonPath('data.sha256', hash('sha256', API_THEME_PNG));

    auth()->forgetGuards();

    $this->withToken($token)->getJson(themeApiUrl('emberfall/theme-assets'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('reports a file that is not what its name says', function () {
    $token = managementApiToken($this, ManagementScope::RealmsWrite);
    $server = $this->transformHeadersToServerVars(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);

    $this->call('PUT', themeApiUrl('emberfall/theme-assets/logo.png'), server: $server, content: '<html></html>')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('deletes a file and answers 404 once it is gone', function () {
    $token = managementApiToken($this, ManagementScope::RealmsWrite);
    $server = $this->transformHeadersToServerVars(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);

    $this->call('PUT', themeApiUrl('emberfall/theme-assets/logo.png'), server: $server, content: API_THEME_PNG)->assertOk();

    auth()->forgetGuards();

    $this->withToken($token)->deleteJson(themeApiUrl('emberfall/theme-assets/logo.png'))->assertNoContent();

    auth()->forgetGuards();

    $this->withToken($token)->deleteJson(themeApiUrl('emberfall/theme-assets/logo.png'))->assertNotFound();
});
