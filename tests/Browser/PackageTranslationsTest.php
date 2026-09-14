<?php
declare(strict_types=1);

use App\Auth\Models\User;

use function Tests\Helpers\visitAccountAs;

/**
 * i18next only fetches the namespaces named at boot, and a missed package
 * namespace falls back to English on an otherwise translated page, where
 * nothing looks broken enough to notice.
 */
it('renders package strings in the user locale', function () {
    session(['auth.password_confirmed_at' => time()]);

    visitAccountAs(User::factory()->create(['locale' => 'de']))
        ->click('button:has-text("Methode hinzufügen")')
        ->click('[data-test="wizard-next"]')
        // Rendered by the oidc-ui package's own React field, not by the server.
        ->assertSee('Nur für dich, damit du dieses Gerät später wiedererkennst.')
        ->assertNoJavaScriptErrors();
});
