<?php
declare(strict_types=1);

return [
    'heading' => 'Users',
    'description' => 'Users of :realm.',
    'created' => 'User created.',
    'updated' => 'User updated.',
    'columns' => [
        'member' => 'Member',
        'verified' => 'Verified',
        'mfa' => 'MFA',
        'blocked' => 'Blocked',
        'admin' => 'Admin',
        'created-at' => 'Joined',
    ],
    'filters' => [
        'verified' => 'Email verified',
        'blocked' => 'Blocked',
        'mfa' => 'MFA active',
    ],
    'status' => [
        'active' => 'Active',
        'blocked' => 'Blocked',
        'mfa-enabled' => 'Enabled',
        'mfa-disabled' => 'Not enrolled',
    ],
    'create' => [
        'heading' => 'Create user',
        'description' => 'Adds a user to :realm. Invite them to choose their own password, or set one now.',
        'submit' => 'Create user',
    ],
    'fields' => [
        'credentials' => [
            'label' => 'Credentials',
            'invite' => 'Send an invitation email to set a password',
            'password' => 'Set an initial password',
        ],
        'email-verified' => [
            'label' => 'Email verified',
            'help-text' => 'A verified address skips the verification email.',
        ],
    ],
    'detail' => [
        'description' => 'User of :realm.',
        'created-at' => 'Joined',
        'blocked-at' => 'Blocked since',
        'status' => 'Status',
        'mfa' => 'Multi-factor authentication',
        'realm' => 'Realm',
        'realms' => [
            'heading' => 'Realm',
        ],
        'profile' => [
            'heading' => 'Profile',
            'subtitle' => 'Name, email address, verification state and realm membership.',
        ],
        'realm-roles' => [
            'heading' => 'Realm roles',
            'subtitle' => 'Issued as the roles claim in every ID and access token of this user.',
            'none' => 'No realm roles assigned.',
            'define' => 'Define the roles of this realm first',
            'field' => [
                'label' => 'Roles',
            ],
            'update' => 'Update realm roles',
            'updated' => 'Realm roles updated.',
        ],
        'resend-verification' => 'Resend verification',
        'verification-sent' => 'Verification email sent.',
        'send-password-reset' => 'Send password reset',
        'password-reset-sent' => 'Password reset email sent.',
        'block' => [
            'label' => 'Block user',
            'confirm' => [
                'title' => 'Block this user?',
                'description' => 'The user can no longer sign in, and every active session and token stops working.',
            ],
            'done' => 'User blocked.',
        ],
        'unblock' => [
            'label' => 'Unblock user',
            'confirm' => [
                'title' => 'Unblock this user?',
                'description' => 'The user can sign in again.',
            ],
            'done' => 'User unblocked.',
        ],
        'reset-mfa' => [
            'label' => 'Reset MFA',
            'confirm' => [
                'title' => 'Reset multi-factor authentication?',
                'description' => 'Removes every authenticator app, recovery code and passkey. The user signs in with their password alone until they enroll again.',
            ],
            'done' => 'Multi-factor authentication reset.',
        ],
        'sessions' => [
            'heading' => 'Sessions',
            'subtitle' => 'Sign-in sessions of this user and the clients that took part in them.',
            'empty' => 'No sessions yet.',
            'not-active' => 'This session is no longer active.',
            'columns' => [
                'sid' => 'Session',
                'status' => 'Status',
                'clients' => 'Clients',
                'started-at' => 'Started',
                'expires-at' => 'Expires',
            ],
            'status' => [
                'active' => 'Active',
                'expired' => 'Expired',
                'revoked' => 'Ended',
            ],
            'end' => [
                'label' => 'End',
                'confirm' => [
                    'title' => 'End this session?',
                    'description' => 'Clients that took part in the session are told to log the user out.',
                ],
                'done' => 'Session ended.',
            ],
            'end-all' => [
                'label' => 'End all sessions',
                'confirm' => [
                    'title' => 'End all sessions?',
                    'description' => 'The user is signed out everywhere and clients are told to log them out.',
                ],
                'done' => ':count sessions ended.',
            ],
        ],
        'delete' => [
            'heading' => 'Delete user',
            'subtitle' => 'Deletes the user together with their sessions, tokens, consents and authentication factors. This cannot be undone.',
            'label' => 'Delete user',
            'confirm' => [
                'title' => 'Delete this user?',
                'description' => 'Everything the user owns in this realm is removed for good.',
            ],
            'done' => 'User deleted.',
        ],
    ],
    'already-verified' => 'This user has already verified their email address.',
    'already-blocked' => 'This user is already blocked.',
    'not-blocked' => 'This user is not blocked.',
];
