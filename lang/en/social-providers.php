<?php
declare(strict_types=1);

return [
    'heading' => 'Providers',
    'description' => 'Upstream identity providers this realm brokers sign-ins to. Add "Social provider" to the accepted sign-in methods to show them on the sign-in page.',
    'created' => 'Provider added.',
    'updated' => 'Provider updated.',
    'deleted' => 'Provider deleted.',
    'linking' => [
        'heading' => 'Account linking',
    ],
    'detail' => [
        'description' => ':driver provider of :realm.',
        'created-at' => 'Added',
        'key-fixed' => 'Part of the redirect URI and of the accounts linked through it, so it is fixed for life.',
        'settings' => [
            'heading' => 'Provider',
            'subtitle' => 'Open a row to change it. The key and the driver are fixed for life.',
        ],
        'secret' => [
            'set' => 'Stored',
            'missing' => 'Not set',
        ],
    ],
    'columns' => [
        'key' => 'Key',
        'driver' => 'Provider',
        'enabled' => 'Enabled',
    ],
    'drivers' => [
        'google' => 'Google',
        'apple' => 'Apple',
        'github' => 'GitHub',
        'oidc' => 'OpenID Connect',
    ],
    'create' => [
        'label' => 'Add provider',
    ],
    'delete' => [
        'label' => 'Delete provider',
        'confirm' => [
            'title' => 'Delete this provider?',
            'description' => 'Deletes the provider together with every account linked through it. Users who only sign in this way lose their way in.',
        ],
    ],
    'fields' => [
        'key' => [
            'label' => 'Key',
            'help-text' => 'Identifies the provider in its URLs and cannot be changed later. Its redirect URI is :url.',
        ],
        'driver' => [
            'label' => 'Provider',
        ],
        'callback-url' => [
            'label' => 'Redirect URI',
            'help-text' => 'Register this URI with the upstream provider.',
        ],
        'issuer' => [
            'label' => 'Issuer URL',
            'help-text' => 'The origin serving /.well-known/openid-configuration, e.g. https://login.example.com.',
        ],
        'client-id' => [
            'label' => 'Client ID',
            'help-text' => 'The client the upstream provider issued for this realm. For Apple it is the Services ID.',
        ],
        'client-secret' => [
            'label' => 'Client secret',
            'help-text' => 'The secret that goes with the client ID.',
        ],
        'team-id' => [
            'label' => 'Team ID',
            'help-text' => 'The Apple Developer team the Services ID belongs to.',
        ],
        'key-id' => [
            'label' => 'Key ID',
            'help-text' => 'The identifier of the Sign in with Apple private key.',
        ],
        'private-key' => [
            'label' => 'Private key',
            'help-text' => 'The contents of the .p8 key file Apple issued. Apple derives the client secret from it.',
        ],
        'secret' => [
            'help-text' => 'Stored encrypted and never shown again. Leave empty to keep the current one.',
        ],
        'enabled' => [
            'label' => 'Enabled',
            'help-text' => 'A disabled provider is closed rather than hidden: its sign-in URLs stop answering.',
        ],
    ],
];
