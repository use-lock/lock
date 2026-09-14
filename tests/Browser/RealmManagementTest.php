<?php

declare(strict_types=1);

use App\Realms\Models\Realm;

use function Tests\Helpers\domainReachesThisInstance;
use function Tests\Helpers\globalAdmin;

it('creates a realm and saves its settings one disclosure row at a time', function () {
    $this->actingAs(globalAdmin());
    domainReachesThisInstance();

    visit('/admin/realms')
        ->click('Create realm')
        ->fill('input[name="name"]', 'Partner realm')
        ->fill('input[name="slug"]', 'partners')
        ->fill('input[name="domain"]', 'auth.partners.test')
        ->click('[data-lattice-form="admin.realms.create"]')
        ->assertSee('Realm created.')
        ->assertPathIs('/admin/realms/partners/settings')
        ->assertMissing('input[name="access_token_lifetime"]')
        ->click('[data-test="setting-access-token-lifetime"] button')
        ->fill('input[name="access_token_lifetime"]', '300')
        ->click('[data-test="setting-access-token-lifetime"] [data-test="form-submit"]')
        ->assertSee('Realm configuration saved.')
        ->assertSee('5 minutes')
        ->click('Client rules')
        ->click('[data-test="setting-allowed-redirect-domains"] button')
        ->fill('textarea[name="allowed_redirect_domains"]', "partner.test\nlocalhost")
        ->click('[data-test="setting-allowed-redirect-domains"] [data-test="form-submit"]')
        ->assertSee('Realm configuration saved.')
        ->assertSee('partner.test')
        ->assertNoJavaScriptErrors();

    $realm = Realm::query()->where('slug', 'partners')->sole();

    expect($realm->tokens()->accessTokenLifetime)->toBe(300)
        ->and($realm->clients()->allowedRedirectDomains)->toBe(['partner.test', 'localhost']);
});
