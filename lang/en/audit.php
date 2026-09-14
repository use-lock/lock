<?php
declare(strict_types=1);

return [
    'admin' => [
        'categories' => [
            'realm' => 'Realm',
            'user' => 'User',
            'client' => 'Client',
            'role' => 'Role',
            'resource' => 'Resource',
        ],
        'types' => [
            'role' => [
                'created' => 'Role created',
                'updated' => 'Role updated',
                'deleted' => 'Role deleted',
                'assignments-updated' => 'Roles assigned to a user',
            ],
            'client' => [
                'created' => 'Client created',
                'updated' => 'Client updated',
                'secret-revealed' => 'Client secret revealed',
                'secret-rotated' => 'Client secret rotated',
                'revoked' => 'Client blocked',
                'restored' => 'Client unblocked',
                'deleted' => 'Client deleted',
            ],
            'realm' => [
                'social-provider-created' => 'Social provider created',
                'social-provider-updated' => 'Social provider updated',
                'social-provider-deleted' => 'Social provider deleted',
                'created' => 'Realm created',
                'updated' => 'Realm updated',
                'deleted' => 'Realm deleted',
            ],
            'resource' => [
                'created' => 'Resource created',
                'updated' => 'Resource updated',
                'deleted' => 'Resource deleted',
                'scope' => [
                    'created' => 'Resource scope created',
                    'updated' => 'Resource scope updated',
                    'deleted' => 'Resource scope deleted',
                ],
            ],
            'user' => [
                'created' => 'User created',
                'updated' => 'User updated',
                'blocked' => 'User blocked',
                'unblocked' => 'User unblocked',
                'deleted' => 'User deleted',
                'mfa-reset' => 'Multi-factor authentication reset',
                'session-ended' => 'Session ended',
                'sessions-ended' => 'All sessions ended',
                'password-reset' => ['sent' => 'Password reset sent'],
                'super-admin-granted' => 'Super Admin granted',
                'verification' => ['resent' => 'Verification email resent'],
            ],
        ],
    ],
    'user' => [
        'categories' => [
            'auth' => 'Authentication',
            'oauth' => 'OAuth',
            'admin' => 'Administration',
        ],
        'types' => [
            'auth' => [
                'login' => [
                    'succeeded' => 'Sign-in succeeded',
                    'failed' => 'Sign-in failed',
                ],
                'logout' => 'Signed out',
                'password' => ['reset' => 'Password reset', 'changed' => 'Password changed'],
                'registration' => ['succeeded' => 'Registration completed'],
                'mfa' => [
                    'challenge-succeeded' => 'Second factor accepted',
                    'challenge-failed' => 'Second factor rejected',
                    'factor-enrollment-started' => 'Factor enrollment started',
                    'factor-confirmed' => 'Factor confirmed',
                    'factor-revoked' => 'Factor revoked',
                    'recovery-code-used' => 'Recovery code used',
                ],
            ],
            'oauth' => [
                'token' => [
                    'issued' => 'Token issued',
                    'failed' => 'Token request failed',
                    'revoked' => 'Token revoked',
                ],
                'consent' => [
                    'approved' => 'Consent granted',
                    'denied' => 'Consent denied',
                ],
                'client-auth' => ['failed' => 'Client authentication failed'],
            ],
            'admin' => [
                'client' => [
                    'provisioned' => 'Client provisioned',
                    'registered' => 'Client registered',
                ],
                'keys' => ['rotated' => 'Signing keys rotated'],
            ],
        ],
    ],
    'events' => [
        'system-actor' => 'System (console)',
        'columns' => [
            'when' => 'When',
            'actor' => 'Actor',
            'type' => 'Event',
            'description' => 'Description',
            'ip' => 'IP address',
        ],
        'filters' => [
            'category' => 'Category',
            'type' => 'Event',
            'failure' => 'Failed',
        ],
        'detail' => [
            'empty' => 'No additional details recorded.',
            'client' => 'Client',
            'session' => 'Session',
            'ip' => 'IP address',
            'user-agent' => 'User agent',
        ],
    ],
    'pages' => [
        'instance' => [
            'heading' => 'Admin events',
            'empty' => 'No admin events yet.',
        ],
        'realm-admin' => [
            'heading' => 'Admin events',
            'description' => 'Configuration changes made to :realm.',
            'empty' => 'No admin events for this realm yet.',
        ],
        'realm-user' => [
            'heading' => 'User events',
            'description' => 'Sign-ins, multi-factor challenges and token activity in :realm.',
            'empty' => 'No user events recorded yet.',
        ],
    ],
];
