<?php
declare(strict_types=1);

test('the login page refuses to be framed by another origin', function () {
    $this->get(route('identity.login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
