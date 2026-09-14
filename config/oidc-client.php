<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('OIDC_RP_ENABLED', true),

    'issuer' => env('OIDC_RP_ISSUER', rtrim((string) env('APP_URL'), '/')),

    'redirect_after_login' => env('OIDC_RP_HOME', '/'),

    'post_logout_redirect_uri' => env('OIDC_RP_POST_LOGOUT_REDIRECT_URI', env('APP_URL')),
];
