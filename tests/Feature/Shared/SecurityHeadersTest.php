<?php
declare(strict_types=1);

test('the login page refuses to be framed by another origin', function () {
    $this->get(route('identity.login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
});

test('every response forbids content sniffing and cross-origin referrer paths', function () {
    $this->get(route('identity.login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $this->getJson('/api/v1/realms')
        ->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('HTTPS responses pin the host to HTTPS without claiming its subdomains', function () {
    $this->get(str_replace('http://', 'https://', route('identity.login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

    $this->get(str_replace('https://', 'http://', route('identity.login')))
        ->assertHeaderMissing('Strict-Transport-Security');
});
