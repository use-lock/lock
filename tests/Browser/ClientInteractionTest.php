<?php

declare(strict_types=1);

use App\Realms\Models\Realm;

use function Tests\Helpers\assumeNotNull;
use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\realmClient;

it('reveals the client secret only on demand and closes it on a fresh visit', function (bool $mobile) {
    $realm = Realm::factory()->create();
    $client = realmClient($realm);
    $this->actingAs(globalAdmin());
    $secret = $client->secret;
    assumeNotNull($secret);

    $url = "/admin/realms/{$realm->slug}/clients/{$client->id}";
    $page = visit($url);

    if ($mobile) {
        $page->resize(390, 844);
    }

    $page->assertMissing('[data-test="client-secret-value"]')
        ->click('Reveal client secret')
        ->assertVisible('[data-test="client-secret-value"]')
        ->assertSee($secret)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->screenshot(filename: $mobile ? 'client-secret-modal-mobile' : 'client-secret-modal-desktop')
        ->click('[data-test="dialog-close"]')
        ->assertMissing('[data-test="client-secret-value"]')
        ->click('Reveal client secret')
        ->assertVisible('[data-test="client-secret-value"]')
        ->refresh()
        ->assertMissing('[data-test="client-secret-value"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->screenshot(filename: $mobile ? 'client-detail-mobile' : 'client-detail-desktop')
        ->assertNoJavaScriptErrors();
})->with(['desktop' => false, 'mobile' => true]);

it('adds edits and removes client redirect uri rows', function () {
    $realm = Realm::factory()->create();
    $client = realmClient($realm);
    $this->actingAs(globalAdmin());

    visit("/admin/realms/{$realm->slug}/clients/{$client->id}")
        ->click('button[aria-controls="client-redirect-uris-panel"]')
        ->assertValue('input[name="redirect_uris[0][uri]"]', 'https://rp.test/callback')
        ->fill('input[name="redirect_uris[0][uri]"]', 'https://portal.test/first')
        ->click('[data-test="repeater-redirect_uris-add"]')
        ->fill('input[name="redirect_uris[1][uri]"]', 'https://portal.test/second')
        ->screenshot(filename: 'client-redirect-repeater')
        ->click('[data-test="client-redirect-uris"] [data-test="form-submit"]')
        ->assertSeeIn('[data-test="client-redirect-uris"]', 'https://portal.test/second')
        ->refresh()
        ->click('button[aria-controls="client-redirect-uris-panel"]')
        ->assertValue('input[name="redirect_uris[1][uri]"]', 'https://portal.test/second')
        ->click('[data-test="repeater-redirect_uris-row-0"] [data-test="row-action-remove"]')
        ->click('[data-test="client-redirect-uris"] [data-test="form-submit"]')
        ->assertDontSeeIn('[data-test="client-redirect-uris"]', 'https://portal.test/first')
        ->assertNoJavaScriptErrors();

    expect($client->refresh()->redirect_uris)->toBe(['https://portal.test/second']);
});
