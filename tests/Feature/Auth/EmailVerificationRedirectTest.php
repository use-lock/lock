<?php
declare(strict_types=1);

use App\Auth\Models\User;

it('sends unverified users to the verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('identity.verification.notice'));
});
