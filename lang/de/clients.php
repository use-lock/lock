<?php
declare(strict_types=1);

return [
    'heading' => 'Clients',
    'description' => 'OIDC-Clients von :realm.',
    'created' => 'Client angelegt.',
    'updated' => 'Client aktualisiert.',
    'deleted' => 'Client gelöscht.',
    'columns' => [
        'name' => 'Name',
        'client-id' => 'Client-ID',
        'type' => 'Typ',
        'grant-types' => 'Grant-Typen',
        'status' => 'Status',
    ],
    'status' => [
        'active' => 'Aktiv',
        'blocked' => 'Gesperrt',
    ],
    'type-badges' => [
        'public' => 'Öffentlich',
        'confidential' => 'Vertraulich',
    ],
    'types' => [
        'none' => 'Öffentlich (kein Secret, nur PKCE)',
        'client-secret-basic' => 'Vertraulich (client_secret_basic)',
        'client-secret-post' => 'Vertraulich (client_secret_post)',
    ],
    'grant-types' => [
        'authorization-code' => 'Authorization Code (PKCE)',
        'refresh-token' => 'Refresh Token',
        'client-credentials' => 'Client Credentials',
        'personal-access' => 'Persönlicher Zugriff',
    ],
    'create' => [
        'heading' => 'Client anlegen',
        'description' => 'Der Client wird in :realm mit den Standard- und optionalen Scopes des Realms angelegt.',
        'submit' => 'Client anlegen',
    ],
    'sections' => [
        'general' => 'Client',
    ],
    'fields' => [
        'name' => [
            'label' => 'Name',
        ],
        'type' => [
            'label' => 'Client-Typ',
            'help-text' => 'Vertrauliche Clients authentifizieren sich am Token-Endpunkt mit einem Secret; öffentliche Clients verlassen sich allein auf PKCE.',
        ],
        'grant-types' => [
            'label' => 'Grant-Typen',
            'help-text' => 'Refresh Tokens setzen den Authorization-Code-Grant voraus. Client Credentials setzen einen vertraulichen Client voraus.',
        ],
        'redirect-uris' => [
            'label' => 'Redirect-URIs',
            'help-text' => 'Jede absolute URI einzeln hinzufügen. Für den Authorization-Code-Grant erforderlich.',
            'add' => 'Redirect-URI hinzufügen',
        ],
        'post-logout-redirect-uris' => [
            'label' => 'Post-Logout-Redirect-URIs',
            'help-text' => 'Jede absolute URI, die der Client nach dem Abmelden verwenden darf, einzeln hinzufügen.',
            'add' => 'Post-Logout-URI hinzufügen',
        ],
        'consent-required' => [
            'label' => 'Einwilligung erforderlich',
            'help-text' => 'Benutzer müssen die angeforderten Scopes bestätigen, bevor Tokens ausgestellt werden.',
        ],
        'backchannel-logout-uri' => [
            'label' => 'Back-Channel-Logout-URI',
            'help-text' => 'Endpunkt, der ein Logout-Token erhält, wenn die Sitzung des Benutzers endet.',
        ],
    ],
    'validation' => [
        'refresh-token-needs-code' => 'Der Refresh-Token-Grant setzt den Authorization-Code-Grant voraus.',
        'client-credentials-needs-secret' => 'Der Client-Credentials-Grant setzt einen vertraulichen Client voraus.',
        'redirect-uri-required' => 'Für den Authorization-Code-Grant ist mindestens eine Redirect-URI erforderlich.',
        'invalid-uri' => 'Die URI :uri muss absolut sein, Schema und Host enthalten und kein Fragment haben.',
    ],
    'detail' => [
        'description' => 'Client von :realm.',
        'created-at' => 'Erstellt',
        'scopes' => 'Scopes',
        'secret' => [
            'heading' => 'Client-Secret',
            'subtitle' => 'Zum Kopieren das Secret aufklappen. Bei einem Leck rotieren.',
            'reveal' => 'Client-Secret anzeigen',
            'label' => 'Client-Secret',
        ],
        'settings' => [
            'heading' => 'Einstellungen',
            'subtitle' => 'Öffnen Sie eine Zeile, um die Einstellung zu ändern. Client-ID, Scopes und Status sind fest.',
        ],
        'rotate-secret' => [
            'label' => 'Secret rotieren',
            'done' => 'Client-Secret rotiert.',
            'confirm' => [
                'title' => 'Client-Secret rotieren?',
                'description' => 'Das aktuelle Secret funktioniert sofort nicht mehr. Das neue kann auf dieser Seite angezeigt werden.',
            ],
        ],
        'revoke' => [
            'label' => 'Client sperren',
            'done' => 'Client gesperrt.',
            'confirm' => [
                'title' => 'Diesen Client sperren?',
                'description' => 'Der Client kann keine Anmeldungen mehr starten und keine Tokens mehr erhalten.',
            ],
        ],
        'restore' => [
            'label' => 'Client entsperren',
            'done' => 'Client entsperrt.',
            'confirm' => [
                'title' => 'Diesen Client entsperren?',
                'description' => 'Der Client kann wieder Anmeldungen starten und Tokens erhalten.',
            ],
        ],
        'delete' => [
            'label' => 'Client löschen',
            'confirm' => [
                'title' => 'Diesen Client löschen?',
                'description' => 'Löscht den Client samt Tokens, Einwilligungen und Sitzungen. Das lässt sich nicht rückgängig machen.',
            ],
        ],
    ],
];
