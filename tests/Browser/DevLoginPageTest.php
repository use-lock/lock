<?php

declare(strict_types=1);

use App\Auth\AuthServiceProvider;

it('prefills development credentials only in the local environment', function (string $environment, string $email, string $password) {
    app()->detectEnvironment(fn (): string => $environment);
    new AuthServiceProvider(app())->register();

    visit('/auth/login')
        ->assertValue('input[name="email"]', $email)
        ->assertValue('input[name="password"]', $password)
        ->assertNoJavaScriptErrors();
})->with([
    'local' => ['local', 'test@example.com', 'password'],
    'production' => ['production', '', ''],
]);
