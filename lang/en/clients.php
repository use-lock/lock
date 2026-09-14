<?php
declare(strict_types=1);

return [
    'heading' => 'Clients',
    'description' => 'OIDC clients of :realm.',
    'created' => 'Client created.',
    'updated' => 'Client updated.',
    'deleted' => 'Client deleted.',
    'columns' => [
        'name' => 'Name',
        'client-id' => 'Client ID',
        'type' => 'Type',
        'grant-types' => 'Grant types',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Active',
        'blocked' => 'Blocked',
    ],
    'type-badges' => [
        'public' => 'Public',
        'confidential' => 'Confidential',
    ],
    'types' => [
        'none' => 'Public (no secret, PKCE only)',
        'client-secret-basic' => 'Confidential (client_secret_basic)',
        'client-secret-post' => 'Confidential (client_secret_post)',
    ],
    'grant-types' => [
        'authorization-code' => 'Authorization code (PKCE)',
        'refresh-token' => 'Refresh token',
        'client-credentials' => 'Client credentials',
        'personal-access' => 'Personal access',
    ],
    'create' => [
        'heading' => 'Create client',
        'description' => 'The client is created in :realm with the realm\'s default and optional scopes.',
        'submit' => 'Create client',
    ],
    'sections' => [
        'general' => 'Client',
    ],
    'fields' => [
        'name' => [
            'label' => 'Name',
        ],
        'type' => [
            'label' => 'Client type',
            'help-text' => 'Confidential clients authenticate at the token endpoint with a secret; public clients rely on PKCE alone.',
        ],
        'grant-types' => [
            'label' => 'Grant types',
            'help-text' => 'Refresh tokens require the authorization code grant. Client credentials require a confidential client.',
        ],
        'redirect-uris' => [
            'label' => 'Redirect URIs',
            'help-text' => 'Add each absolute URI separately. Required for the authorization code grant.',
            'add' => 'Add redirect URI',
        ],
        'post-logout-redirect-uris' => [
            'label' => 'Post-logout redirect URIs',
            'help-text' => 'Add each absolute URI the client may use after logout separately.',
            'add' => 'Add post-logout URI',
        ],
        'consent-required' => [
            'label' => 'Require consent',
            'help-text' => 'Ask users to approve the requested scopes before issuing tokens.',
        ],
        'backchannel-logout-uri' => [
            'label' => 'Back-channel logout URI',
            'help-text' => 'Endpoint that receives a logout token when the user\'s session ends.',
        ],
    ],
    'validation' => [
        'refresh-token-needs-code' => 'The refresh token grant requires the authorization code grant.',
        'client-credentials-needs-secret' => 'The client credentials grant requires a confidential client.',
        'redirect-uri-required' => 'At least one redirect URI is required for the authorization code grant.',
        'invalid-uri' => 'The URI :uri must be absolute with a scheme and a host and no fragment.',
    ],
    'detail' => [
        'description' => 'Client of :realm.',
        'created-at' => 'Created',
        'scopes' => 'Scopes',
        'secret' => [
            'heading' => 'Client secret',
            'subtitle' => 'Reveal the secret when you need to copy it. Rotate it if it leaks.',
            'reveal' => 'Reveal client secret',
            'label' => 'Client secret',
        ],
        'settings' => [
            'heading' => 'Settings',
            'subtitle' => 'Open a row to change that setting. Client ID, scopes and status are fixed.',
        ],
        'rotate-secret' => [
            'label' => 'Rotate secret',
            'done' => 'Client secret rotated.',
            'confirm' => [
                'title' => 'Rotate the client secret?',
                'description' => 'The current secret stops working immediately. You can reveal the new one on this page.',
            ],
        ],
        'revoke' => [
            'label' => 'Block client',
            'done' => 'Client blocked.',
            'confirm' => [
                'title' => 'Block this client?',
                'description' => 'The client can no longer start logins or obtain tokens.',
            ],
        ],
        'restore' => [
            'label' => 'Unblock client',
            'done' => 'Client unblocked.',
            'confirm' => [
                'title' => 'Unblock this client?',
                'description' => 'The client can start logins and obtain tokens again.',
            ],
        ],
        'delete' => [
            'label' => 'Delete client',
            'confirm' => [
                'title' => 'Delete this client?',
                'description' => 'Deletes the client together with its tokens, consents and sessions. This cannot be undone.',
            ],
        ],
    ],
];
