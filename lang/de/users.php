<?php
declare(strict_types=1);

return [
    'heading' => 'Benutzer',
    'description' => 'Benutzer von :realm.',
    'created' => 'Benutzer angelegt.',
    'updated' => 'Benutzer aktualisiert.',
    'columns' => [
        'member' => 'Mitglied',
        'verified' => 'Verifiziert',
        'mfa' => 'MFA',
        'blocked' => 'Gesperrt',
        'admin' => 'Admin',
        'created-at' => 'Beigetreten',
    ],
    'filters' => [
        'verified' => 'E-Mail verifiziert',
        'blocked' => 'Gesperrt',
        'mfa' => 'MFA aktiv',
    ],
    'status' => [
        'active' => 'Aktiv',
        'blocked' => 'Gesperrt',
        'mfa-enabled' => 'Aktiviert',
        'mfa-disabled' => 'Nicht eingerichtet',
    ],
    'create' => [
        'heading' => 'Benutzer anlegen',
        'description' => 'Fügt :realm einen Benutzer hinzu. Laden Sie ihn ein, ein eigenes Passwort zu wählen, oder legen Sie jetzt eines fest.',
        'submit' => 'Benutzer anlegen',
    ],
    'fields' => [
        'credentials' => [
            'label' => 'Zugangsdaten',
            'invite' => 'Einladungs-E-Mail zum Setzen eines Passworts senden',
            'password' => 'Ein initiales Passwort festlegen',
        ],
        'email-verified' => [
            'label' => 'E-Mail verifiziert',
            'help-text' => 'Eine verifizierte Adresse überspringt die Bestätigungs-E-Mail.',
        ],
    ],
    'detail' => [
        'description' => 'Benutzer von :realm.',
        'created-at' => 'Beigetreten',
        'blocked-at' => 'Gesperrt seit',
        'status' => 'Status',
        'mfa' => 'Mehrfaktor-Authentifizierung',
        'realm' => 'Realm',
        'realms' => [
            'heading' => 'Realm',
        ],
        'profile' => [
            'heading' => 'Profil',
            'subtitle' => 'Name, E-Mail-Adresse, Verifizierungsstatus und Realm-Zugehörigkeit.',
        ],
        'realm-roles' => [
            'heading' => 'Realm-Rollen',
            'subtitle' => 'Werden als roles-Claim in jedem ID- und Access-Token dieses Benutzers ausgegeben.',
            'none' => 'Keine Realm-Rollen zugewiesen.',
            'define' => 'Zuerst die Rollen dieses Realms anlegen',
            'field' => [
                'label' => 'Rollen',
            ],
            'update' => 'Realm-Rollen aktualisieren',
            'updated' => 'Realm-Rollen aktualisiert.',
        ],
        'resend-verification' => 'Verifizierung erneut senden',
        'verification-sent' => 'Verifizierungs-E-Mail gesendet.',
        'send-password-reset' => 'Passwort-Reset senden',
        'password-reset-sent' => 'E-Mail zum Zurücksetzen des Passworts gesendet.',
        'block' => [
            'label' => 'Benutzer sperren',
            'confirm' => [
                'title' => 'Diesen Benutzer sperren?',
                'description' => 'Der Benutzer kann sich nicht mehr anmelden, und alle aktiven Sitzungen und Tokens werden ungültig.',
            ],
            'done' => 'Benutzer gesperrt.',
        ],
        'unblock' => [
            'label' => 'Sperre aufheben',
            'confirm' => [
                'title' => 'Sperre dieses Benutzers aufheben?',
                'description' => 'Der Benutzer kann sich wieder anmelden.',
            ],
            'done' => 'Sperre aufgehoben.',
        ],
        'reset-mfa' => [
            'label' => 'MFA zurücksetzen',
            'confirm' => [
                'title' => 'Mehrfaktor-Authentifizierung zurücksetzen?',
                'description' => 'Entfernt alle Authenticator-Apps, Wiederherstellungscodes und Passkeys. Bis zur erneuten Einrichtung meldet sich der Benutzer nur mit seinem Passwort an.',
            ],
            'done' => 'Mehrfaktor-Authentifizierung zurückgesetzt.',
        ],
        'sessions' => [
            'heading' => 'Sitzungen',
            'subtitle' => 'Anmeldesitzungen dieses Benutzers und die daran beteiligten Clients.',
            'empty' => 'Noch keine Sitzungen.',
            'not-active' => 'Diese Sitzung ist nicht mehr aktiv.',
            'columns' => [
                'sid' => 'Sitzung',
                'status' => 'Status',
                'clients' => 'Clients',
                'started-at' => 'Gestartet',
                'expires-at' => 'Läuft ab',
            ],
            'status' => [
                'active' => 'Aktiv',
                'expired' => 'Abgelaufen',
                'revoked' => 'Beendet',
            ],
            'end' => [
                'label' => 'Beenden',
                'confirm' => [
                    'title' => 'Diese Sitzung beenden?',
                    'description' => 'Beteiligte Clients werden aufgefordert, den Benutzer abzumelden.',
                ],
                'done' => 'Sitzung beendet.',
            ],
            'end-all' => [
                'label' => 'Alle Sitzungen beenden',
                'confirm' => [
                    'title' => 'Alle Sitzungen beenden?',
                    'description' => 'Der Benutzer wird überall abgemeldet, und Clients werden aufgefordert, ihn abzumelden.',
                ],
                'done' => ':count Sitzungen beendet.',
            ],
        ],
        'delete' => [
            'heading' => 'Benutzer löschen',
            'subtitle' => 'Löscht den Benutzer samt Sitzungen, Tokens, Zustimmungen und Authentifizierungsfaktoren. Das lässt sich nicht rückgängig machen.',
            'label' => 'Benutzer löschen',
            'confirm' => [
                'title' => 'Diesen Benutzer löschen?',
                'description' => 'Alles, was der Benutzer in diesem Realm besitzt, wird endgültig entfernt.',
            ],
            'done' => 'Benutzer gelöscht.',
        ],
    ],
    'already-verified' => 'Dieser Benutzer hat seine E-Mail-Adresse bereits verifiziert.',
    'already-blocked' => 'Dieser Benutzer ist bereits gesperrt.',
    'not-blocked' => 'Dieser Benutzer ist nicht gesperrt.',
];
