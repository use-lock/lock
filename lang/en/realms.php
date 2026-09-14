<?php
declare(strict_types=1);

return [
    'heading' => 'Realms',
    'description' => 'Every realm is an isolated identity boundary with its own users, clients and authentication settings.',
    'columns' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'domain' => 'Domain',
        'domain-status' => 'Domain status',
        'users' => 'Users',
        'created-at' => 'Created',
    ],
    'created' => 'Realm created.',
    'created-unverified' => 'Realm created. :outcome',
    'domain' => [
        'master-fixed' => 'The administration realm is served from APP_URL and takes no domain of its own.',
        'master' => 'Taken from APP_URL.',
        'status' => [
            'pending' => 'Not checked',
            'verified' => 'Verified',
            'unreachable' => 'Unreachable',
            'misrouted' => 'Wrong server',
        ],
        'outcome' => [
            'pending' => 'Not checked yet.',
            'verified' => ':domain reaches this instance.',
            'unreachable' => ':domain does not answer. Check its DNS record, the proxy entry and the TLS certificate.',
            'misrouted' => ':domain answers, but not as this Lock instance.',
        ],
        'checked-at' => 'Checked :time.',
        'change-warning' => 'The domain is the realm\'s issuer. Changing it signs every user out and every client, redirect URI and passkey of the realm has to follow.',
        'submit' => 'Save domain',
        'updated' => 'Domain changed.',
        'check' => 'Check again',
    ],
    'updated' => 'Realm updated.',
    'configuration-saved' => 'Realm configuration saved.',
    'deleted' => 'Realm deleted.',
    'settings' => [
        'heading' => 'Realm settings',
        'description' => 'Name, authentication configuration and deletion of :realm.',
    ],
    'tabs' => [
        'general' => 'General',
    ],
    'values' => [
        'never' => 'Never',
        'generated' => 'Generated per realm',
    ],
    'summary' => [
        'users' => 'Users',
        'created-at' => 'Created',
    ],
    'general' => [
        'heading' => 'Realm details',
        'subtitle' => 'Open a row to change it. The identifier is fixed for life.',
        'submit' => 'Save realm',
    ],
    'danger' => [
        'heading' => 'Delete realm',
        'subtitle' => 'Deletes the realm together with its users, clients, sessions and tokens. This cannot be undone.',
        'confirm-name' => 'Confirm realm name',
        'name-mismatch' => 'The realm name does not match.',
        'submit' => 'Delete realm',
    ],
    'create' => [
        'heading' => 'Create realm',
        'description' => 'New realms start with the default authentication settings. You can configure them after creating the realm.',
        'submit' => 'Create realm',
    ],
    'sections' => [
        'social' => 'Social sign-in',
        'general' => 'General',
        'login' => 'Sign-in',
        'tokens' => 'Token lifetimes',
        'sessions' => 'Sessions',
        'mfa' => 'Multi-factor authentication',
        'passwords' => 'Passwords',
        'clients' => 'Client rules',
    ],
    'factors' => [
        'totp' => 'Authenticator app (TOTP)',
        'webauthn' => 'Security key or passkey (WebAuthn)',
    ],
    'login-methods' => [
        'password' => 'Email address and password',
        'passkey' => 'Passkey',
        'social' => 'Social provider',
    ],
    'mfa-requirements' => [
        'never' => 'Never',
        'if-enrolled' => 'When the user has a factor',
        'always' => 'Always',
    ],
    'fields' => [
        'name' => [
            'label' => 'Realm name',
        ],
        'slug' => [
            'label' => 'Realm identifier',
            'help-text' => 'Identifies the realm in the console. Lowercase letters, numbers and single hyphens only. Cannot be changed later.',
        ],
        'domain' => [
            'label' => 'Domain',
            'help-text' => 'The host the realm is served from, e.g. auth.example.com. Point its DNS at this server and add it to the platform proxy; the realm is created either way.',
        ],
        'access-token-lifetime' => [
            'label' => 'Access token lifetime',
            'help-text' => 'Lifetime in seconds for newly issued access tokens.',
        ],
        'id-token-lifetime' => [
            'label' => 'ID token lifetime',
            'help-text' => 'Lifetime in seconds for newly issued ID tokens.',
        ],
        'client-credentials-lifetime' => [
            'label' => 'Machine-to-machine token lifetime',
            'help-text' => 'Lifetime in seconds for client credentials tokens.',
        ],
        'refresh-token-lifetime' => [
            'label' => 'Refresh token lifetime',
            'help-text' => 'Lifetime in seconds for newly issued refresh tokens.',
        ],
        'session-absolute-lifetime' => [
            'label' => 'Maximum session lifetime',
            'help-text' => 'Maximum age of an authentication session in seconds.',
        ],
        'session-token-ttl' => [
            'label' => 'Session token lifetime',
            'help-text' => 'Lifetime in seconds for the token associated with a session.',
        ],
        'session-token-refresh-skew' => [
            'label' => 'Session token renewal window',
            'help-text' => 'How many seconds before expiry a session token is renewed.',
        ],
        'login-methods' => [
            'label' => 'Accepted sign-in methods',
            'help-text' => 'Methods this realm offers. A method left out is closed rather than hidden, and registration as well as password reset hang off the password method.',
        ],
        'link-by-verified-email' => [
            'label' => 'Link by verified email address',
            'help-text' => 'Attach an upstream identity to the realm user with the same address, if the provider reports it as verified.',
        ],
        'auto-provision' => [
            'label' => 'Create users on first sign-in',
            'help-text' => 'Create a realm user the first time an unknown identity signs in through a provider. Only a verified upstream address is accepted.',
        ],
        'email-verification-required' => [
            'label' => 'Require a verified email address',
            'help-text' => 'Users with an unconfirmed address have to verify it before their sign-in completes.',
        ],
        'mfa-requirement' => [
            'label' => 'Second factor',
            'help-text' => 'When the realm challenges for a second factor. Always sends a user without one to enrollment before the sign-in completes.',
        ],
        'challenge-providers' => [
            'label' => 'Available second factors',
            'help-text' => 'Factors users can use to complete a multi-factor challenge.',
        ],
        'totp-secret-length' => [
            'label' => 'TOTP secret length',
            'help-text' => 'Length of newly generated authenticator secrets: 16 to 64 characters, in steps of 8.',
        ],
        'totp-window' => [
            'label' => 'TOTP clock tolerance',
            'help-text' => 'Accepted clock drift in 30-second steps.',
        ],
        'recovery-codes' => [
            'label' => 'Recovery code count',
            'help-text' => 'Number of codes generated when users create recovery codes.',
        ],
        'password-min-length' => [
            'label' => 'Minimum password length',
            'help-text' => 'Characters a new password needs at least.',
        ],
        'password-history' => [
            'label' => 'Password history',
            'help-text' => 'Previous passwords a new password may not repeat, including the current one. 0 disables the check.',
        ],
        'password-max-age-days' => [
            'label' => 'Maximum password age',
            'help-text' => 'Days after which a password counts as expired. 0 means passwords never expire.',
        ],
        'password-mixed-case' => [
            'label' => 'Require upper and lower case letters',
            'help-text' => 'A new password must contain at least one upper and one lower case letter.',
        ],
        'password-numbers' => [
            'label' => 'Require a number',
            'help-text' => 'A new password must contain at least one digit.',
        ],
        'password-symbols' => [
            'label' => 'Require a symbol',
            'help-text' => 'A new password must contain at least one special character.',
        ],
        'password-uncompromised' => [
            'label' => 'Reject leaked passwords',
            'help-text' => 'Reject passwords found in a public data breach. This queries the Have I Been Pwned range API when a password is set.',
        ],
        'dynamic-registration' => [
            'label' => 'Allow dynamic client registration',
            'help-text' => 'Allow applications to register through the realm registration endpoint.',
        ],
        'token-exchange' => [
            'label' => 'Allow token exchange',
            'help-text' => 'Allow clients to exchange tokens within this realm.',
        ],
        'first-party-trusted' => [
            'label' => 'Trust the first-party client',
            'help-text' => 'Skip consent for the first-party client when one is configured.',
        ],
        'allowed-redirect-schemes' => [
            'label' => 'Allowed custom redirect schemes',
            'help-text' => 'One scheme per line, without a colon. Leave empty to allow only HTTP and HTTPS schemes.',
        ],
        'allowed-redirect-domains' => [
            'label' => 'Allowed redirect domains',
            'help-text' => 'One exact host per line. Use * to allow any host. An empty list allows no HTTP or HTTPS redirect hosts.',
        ],
        'default-scopes' => [
            'label' => 'Default scopes',
            'help-text' => 'Scopes every new client gets and is granted without requesting them, one per line.',
        ],
        'optional-scopes' => [
            'label' => 'Optional scopes',
            'help-text' => 'Scopes every new client may request, one per line. A single * allows every scope in the catalog.',
        ],
        'trusted-clients' => [
            'label' => 'Trusted clients',
            'help-text' => 'One client identifier from this realm per line. These clients skip consent.',
        ],
    ],
];
