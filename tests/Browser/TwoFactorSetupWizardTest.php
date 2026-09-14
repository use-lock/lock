<?php
declare(strict_types=1);

use App\Auth\Models\User;

use function Tests\Helpers\visitAccountAs;

/**
 * Step two is prepared by Lattice's resolve sub-request, so only a browser
 * shows whether it reaches the field.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    session(['auth.password_confirmed_at' => time()]);
});

it('prepares the authenticator app once it is picked', function () {
    visitAccountAs($this->user)
        ->click('button:has-text("Add method")')
        ->click('text=Authenticator app')
        ->click('[data-test="wizard-next"]')
        ->assertSee('Or enter this setup key in your authenticator app:')
        ->assertNoJavaScriptErrors();
});
