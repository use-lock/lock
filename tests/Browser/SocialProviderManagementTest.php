<?php

declare(strict_types=1);

use App\Realms\Models\Realm;

use function Tests\Helpers\globalAdmin;

it('configures a provider through its detail rows and deletes it from the row menu', function (bool $mobile) {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $this->actingAs(globalAdmin());
    $page = visit('/admin/realms/acme/settings?tabs=social');

    if ($mobile) {
        $page->resize(390, 844);
    }

    $page->click('Add provider')
        ->click('[data-test="select-driver"]')
        ->click('OpenID Connect')
        ->assertVisible('input[name="issuer"]')
        ->click('[data-test="select-driver"]')
        ->click('Google')
        ->fill('input[name="key"]', 'google')
        ->fill('input[name="client_id"]', 'google-client-id')
        ->fill('input[name="client_secret"]', 'initial-secret')
        ->assertMissing('input[name="issuer"]')
        ->click('[data-test="action-form-submit"]')
        ->assertSee('Provider added.')
        ->assertSee('google-client-id')
        ->assertDontSee('initial-secret');

    $provider = $realm->socialProviders()->sole();

    $page->assertPathIs("/admin/realms/acme/social-providers/{$provider->id}")
        ->click('[data-test="social-provider-client-secret"] button')
        ->assertValue('input[name="client_secret"]', '')
        ->fill('input[name="client_secret"]', 'replacement-secret')
        ->click('[data-test="social-provider-client-secret"] [data-test="form-submit"]')
        ->assertSee('Provider updated.')
        ->assertDontSee('replacement-secret')
        ->assertValue('input[name="client_secret"]', '')
        ->click('[data-test="social-provider-client-secret"] button[aria-expanded]')
        ->click('[data-test="social-provider-client-id"] button')
        ->fill('input[name="client_id"]', 'new-client-id')
        ->click('[data-test="social-provider-client-id"] [data-test="form-submit"]')
        ->assertSee('new-client-id')
        ->click('[data-test="social-provider-client-id"] button[aria-expanded]')
        ->navigate("/admin/realms/acme/social-providers/{$provider->id}")
        ->assertSee('new-client-id')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->screenshot(filename: $mobile ? 'social-provider-mobile' : 'social-provider-desktop')
        ->assertNoJavaScriptErrors();

    expect($provider->refresh()->config)->toBe(['client_id' => 'new-client-id', 'client_secret' => 'replacement-secret']);

    $page->navigate('/admin/realms/acme/settings?tabs=social')
        ->click('google')
        ->assertPathIs("/admin/realms/acme/social-providers/{$provider->id}")
        ->navigate('/admin/realms/acme/settings?tabs=social')
        ->click('[data-test="admin.social-providers.row-actions"]')
        ->click('Delete provider')
        ->click('[data-test="confirm-accept"]')
        ->assertSee('Provider deleted.')
        ->assertPathIs('/admin/realms/acme/settings')
        ->assertSee('Add provider')
        ->assertNoJavaScriptErrors();

    expect($realm->socialProviders()->exists())->toBeFalse();
})->with(['desktop' => false, 'mobile' => true]);
