<?php

declare(strict_types=1);

use App\Auth\Models\User;

use function Tests\Helpers\visitAccountAs;

it('reveals the edit form behind an account row and keeps the others closed', function () {
    $page = visitAccountAs(User::factory()->create());

    $page->assertMissing('input[name="name"]')
        ->assertMissing('input[name="current_password"]')
        ->click('[data-test="row-name"] button')
        ->assertVisible('input[name="name"]')
        ->assertMissing('input[name="current_password"]')
        ->assertNoJavaScriptErrors();
});
