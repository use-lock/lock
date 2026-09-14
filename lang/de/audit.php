<?php
declare(strict_types=1);

return [
    'admin' => [
        'categories' => [
            'realm' => 'Realm',
            'user' => 'Benutzer',
            'client' => 'Client',
            'role' => 'Rolle',
            'resource' => 'Resource',
        ],
        'types' => [
            'role' => [
                'created' => 'Rolle angelegt',
                'updated' => 'Rolle aktualisiert',
                'deleted' => 'Rolle gelöscht',
                'assignments-updated' => 'Rollen einem Benutzer zugewiesen',
            ],
            'client' => [
                'created' => 'Client angelegt',
                'updated' => 'Client aktualisiert',
                'secret-revealed' => 'Client-Secret angezeigt',
                'secret-rotated' => 'Client-Secret rotiert',
                'revoked' => 'Client gesperrt',
                'restored' => 'Client entsperrt',
                'deleted' => 'Client gelöscht',
            ],
            'realm' => [
                'social-provider-created' => 'Social-Provider angelegt',
                'social-provider-updated' => 'Social-Provider aktualisiert',
                'social-provider-deleted' => 'Social-Provider gelöscht',
                'created' => 'Realm angelegt',
                'updated' => 'Realm aktualisiert',
                'deleted' => 'Realm gelöscht',
            ],
            'resource' => [
                'created' => 'Resource angelegt',
                'updated' => 'Resource geändert',
                'deleted' => 'Resource gelöscht',
                'scope' => [
                    'created' => 'Resource-Scope angelegt',
                    'updated' => 'Resource-Scope geändert',
                    'deleted' => 'Resource-Scope gelöscht',
                ],
            ],
            'user' => [
                'created' => 'Benutzer angelegt',
                'updated' => 'Benutzer aktualisiert',
                'blocked' => 'Benutzer gesperrt',
                'unblocked' => 'Sperre aufgehoben',
                'deleted' => 'Benutzer gelöscht',
                'mfa-reset' => 'Mehrfaktor-Authentifizierung zurückgesetzt',
                'session-ended' => 'Sitzung beendet',
                'sessions-ended' => 'Alle Sitzungen beendet',
                'password-reset' => ['sent' => 'Passwort-Reset gesendet'],
                'super-admin-granted' => 'Super Admin vergeben',
                'verification' => ['resent' => 'Bestätigungs-E-Mail erneut gesendet'],
            ],
        ],
    ],
    'user' => [
        'categories' => [
            'auth' => 'Authentifizierung',
            'oauth' => 'OAuth',
            'admin' => 'Administration',
        ],
        'types' => [
            'auth' => [
                'login' => [
                    'succeeded' => 'Anmeldung erfolgreich',
                    'failed' => 'Anmeldung fehlgeschlagen',
                ],
                'logout' => 'Abgemeldet',
                'password' => ['reset' => 'Passwort zurückgesetzt', 'changed' => 'Passwort geändert'],
                'registration' => ['succeeded' => 'Registrierung abgeschlossen'],
                'mfa' => [
                    'challenge-succeeded' => 'Zweiter Faktor akzeptiert',
                    'challenge-failed' => 'Zweiter Faktor abgelehnt',
                    'factor-enrollment-started' => 'Faktor-Einrichtung gestartet',
                    'factor-confirmed' => 'Faktor bestätigt',
                    'factor-revoked' => 'Faktor entfernt',
                    'recovery-code-used' => 'Wiederherstellungscode verwendet',
                ],
            ],
            'oauth' => [
                'token' => [
                    'issued' => 'Token ausgestellt',
                    'failed' => 'Token-Anfrage fehlgeschlagen',
                    'revoked' => 'Token widerrufen',
                ],
                'consent' => [
                    'approved' => 'Zustimmung erteilt',
                    'denied' => 'Zustimmung verweigert',
                ],
                'client-auth' => ['failed' => 'Client-Authentifizierung fehlgeschlagen'],
            ],
            'admin' => [
                'client' => [
                    'provisioned' => 'Client bereitgestellt',
                    'registered' => 'Client registriert',
                ],
                'keys' => ['rotated' => 'Signaturschlüssel rotiert'],
            ],
        ],
    ],
    'events' => [
        'system-actor' => 'System (Konsole)',
        'columns' => [
            'when' => 'Wann',
            'actor' => 'Akteur',
            'type' => 'Ereignis',
            'description' => 'Beschreibung',
            'ip' => 'IP-Adresse',
        ],
        'filters' => [
            'category' => 'Kategorie',
            'type' => 'Ereignis',
            'failure' => 'Fehlgeschlagen',
        ],
        'detail' => [
            'empty' => 'Keine weiteren Details aufgezeichnet.',
            'client' => 'Client',
            'session' => 'Sitzung',
            'ip' => 'IP-Adresse',
            'user-agent' => 'User-Agent',
        ],
    ],
    'pages' => [
        'instance' => [
            'heading' => 'Admin-Ereignisse',
            'empty' => 'Noch keine Admin-Ereignisse.',
        ],
        'realm-admin' => [
            'heading' => 'Admin-Ereignisse',
            'description' => 'Konfigurationsänderungen an :realm.',
            'empty' => 'Noch keine Admin-Ereignisse für diesen Realm.',
        ],
        'realm-user' => [
            'heading' => 'Benutzer-Ereignisse',
            'description' => 'Anmeldungen, Zwei-Faktor-Prüfungen und Token-Aktivität in :realm.',
            'empty' => 'Noch keine Benutzer-Ereignisse aufgezeichnet.',
        ],
    ],
];
