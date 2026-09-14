<?php
declare(strict_types=1);

return [
    'title' => 'Konto',
    'sections' => [
        'login' => 'Anmeldung',
    ],
    'profile' => [
        'subtitle' => 'Verwalte dein Profil, deine Anmeldesicherheit und deine Browser-Sitzungen.',
        'updated' => 'Profil aktualisiert.',
        'unverified' => 'Deine E-Mail-Adresse ist nicht bestätigt.',
        'email' => [
            'unverified' => 'Nicht bestätigt',
        ],
        'verification-sent' => 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',
        'delete-account' => [
            'heading' => 'Konto löschen',
            'description' => 'Lösche dein Konto und alle zugehörigen Ressourcen. Diese Aktion kann nicht rückgängig gemacht werden. Gib dein Passwort zur Bestätigung ein.',
            'submit' => 'Konto löschen',
        ],
    ],
    'security' => [
        'heading' => 'Sicherheit',
        'subtitle' => 'Aktualisiere dein Passwort und verwalte die Anmeldesicherheit.',
        'confirm' => [
            'description' => 'Bestätige dein Passwort, um deine Sicherheitseinstellungen anzuzeigen und zu verwalten.',
            'action' => 'Sicherheitseinstellungen entsperren',
        ],
        'password' => [
            'current' => 'Aktuelles Passwort',
            'new' => 'Neues Passwort',
            'updated' => 'Passwort aktualisiert.',
        ],
        'two-factor' => [
            'heading' => 'Zwei-Faktor-Authentifizierung',
            'status' => [
                'enabled' => 'Aktiv',
                'disabled' => 'Nicht aktiv',
            ],
            'add-method' => 'Methode hinzufügen',
            'description' => [
                'enabled' => 'Bei der Anmeldung fragen wir nach dem Passwort zusätzlich nach einer dieser Methoden.',
                'disabled' => 'Nur dein Passwort schützt dieses Konto. Füge eine Methode hinzu, um bei der Anmeldung einen zweiten Schritt zu verlangen.',
            ],
            'setup-title' => 'Zweiten Faktor hinzufügen',
        ],
        'recovery-codes' => [
            'heading' => 'Wiederherstellungscodes',
        ],
    ],
    'sessions' => [
        'heading' => 'Browser-Sitzungen',
        'description' => 'Alle Browser, die aktuell in diesem Konto angemeldet sind. Melde jede Sitzung ab, die du nicht kennst.',
        'empty' => 'Keine weiteren Browser-Sitzungen.',
        'unknown-device' => 'Unbekanntes Gerät',
        'columns' => [
            'device' => 'Gerät',
            'last-active' => 'Zuletzt aktiv',
            'status' => 'Status',
        ],
        'status' => [
            'current' => 'Dieses Gerät',
            'active' => 'Aktiv',
        ],
        'sign-out' => [
            'label' => 'Abmelden',
            'confirm' => [
                'title' => 'Diese Sitzung abmelden?',
                'description' => 'Dieser Browser muss sich erneut anmelden, und Anwendungen, in denen er angemeldet war, werden aufgefordert, ihre Sitzungen zu beenden.',
            ],
            'done' => 'Die Sitzung wurde abgemeldet.',
            'missing' => 'Diese Sitzung ist bereits beendet.',
        ],
    ],
    'preferences' => [
        'heading' => 'Einstellungen',
        'subtitle' => 'Wähle dein Design, deine Sprache und deine Zeitzone.',
        'theme' => [
            'label' => 'Darstellung',
            'light' => 'Hell',
            'dark' => 'Dunkel',
            'system' => 'System',
        ],
        'language' => [
            'label' => 'Sprache',
        ],
        'timezone' => [
            'label' => 'Zeitzone',
            'placeholder' => 'Zeitzone auswählen',
        ],
        'updated' => 'Einstellungen aktualisiert.',
    ],
];
