<?php
declare(strict_types=1);

return [
    'heading' => 'Realms',
    'description' => 'Jeder Realm ist eine eigene Identitätsgrenze mit eigenen Benutzern, Clients und Authentifizierungseinstellungen.',
    'columns' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'domain' => 'Domain',
        'domain-status' => 'Domain-Status',
        'users' => 'Benutzer',
        'created-at' => 'Erstellt',
    ],
    'created' => 'Realm angelegt.',
    'created-unverified' => 'Realm angelegt. :outcome',
    'domain' => [
        'master-fixed' => 'Der Administrationsrealm wird über APP_URL ausgeliefert und hat keine eigene Domain.',
        'master' => 'Aus APP_URL übernommen.',
        'status' => [
            'pending' => 'Nicht geprüft',
            'verified' => 'Verifiziert',
            'unreachable' => 'Nicht erreichbar',
            'misrouted' => 'Falscher Server',
        ],
        'outcome' => [
            'pending' => 'Noch nicht geprüft.',
            'verified' => ':domain erreicht diese Instanz.',
            'unreachable' => ':domain antwortet nicht. Prüfe den DNS-Eintrag, den Proxy-Eintrag und das TLS-Zertifikat.',
            'misrouted' => ':domain antwortet, aber nicht als diese Lock-Instanz.',
        ],
        'checked-at' => 'Geprüft :time.',
        'change-warning' => 'Die Domain ist der Issuer des Realms. Eine Änderung meldet alle Benutzer ab, und alle Clients, Redirect-URIs und Passkeys des Realms müssen nachziehen.',
        'submit' => 'Domain speichern',
        'updated' => 'Domain geändert.',
        'check' => 'Erneut prüfen',
    ],
    'updated' => 'Realm aktualisiert.',
    'configuration-saved' => 'Realm-Konfiguration gespeichert.',
    'deleted' => 'Realm gelöscht.',
    'settings' => [
        'heading' => 'Realm-Einstellungen',
        'description' => 'Name, Authentifizierungskonfiguration und Löschung von :realm.',
    ],
    'tabs' => [
        'general' => 'Allgemein',
    ],
    'values' => [
        'never' => 'Nie',
        'generated' => 'Pro Realm generiert',
    ],
    'summary' => [
        'users' => 'Benutzer',
        'created-at' => 'Erstellt',
    ],
    'general' => [
        'heading' => 'Realm-Details',
        'subtitle' => 'Öffnen Sie eine Zeile, um sie zu ändern. Die Kennung bleibt dauerhaft fest.',
        'submit' => 'Realm speichern',
    ],
    'danger' => [
        'heading' => 'Realm löschen',
        'subtitle' => 'Löscht den Realm samt Benutzern, Clients, Sitzungen und Tokens. Das lässt sich nicht rückgängig machen.',
        'confirm-name' => 'Realm-Namen bestätigen',
        'name-mismatch' => 'Der Realm-Name stimmt nicht überein.',
        'submit' => 'Realm löschen',
    ],
    'create' => [
        'heading' => 'Realm anlegen',
        'description' => 'Neue Realms starten mit den Standardeinstellungen für die Authentifizierung. Nach dem Anlegen können Sie diese konfigurieren.',
        'submit' => 'Realm anlegen',
    ],
    'sections' => [
        'design' => 'Design',
        'social' => 'Social-Anmeldung',
        'general' => 'Allgemein',
        'login' => 'Anmeldung',
        'tokens' => 'Token-Laufzeiten',
        'sessions' => 'Sitzungen',
        'mfa' => 'Mehrfaktor-Authentifizierung',
        'passwords' => 'Passwörter',
        'clients' => 'Client-Regeln',
    ],
    'factors' => [
        'totp' => 'Authenticator-App (TOTP)',
        'webauthn' => 'Sicherheitsschlüssel oder Passkey (WebAuthn)',
    ],
    'login-methods' => [
        'password' => 'E-Mail-Adresse und Passwort',
        'passkey' => 'Passkey',
        'social' => 'Social-Login-Anbieter',
    ],
    'mfa-requirements' => [
        'never' => 'Nie',
        'if-enrolled' => 'Wenn der Benutzer einen Faktor hat',
        'always' => 'Immer',
    ],
    'fields' => [
        'name' => [
            'label' => 'Realm-Name',
        ],
        'slug' => [
            'label' => 'Realm-Kennung',
            'help-text' => 'Kennzeichnet den Realm in der Konsole. Nur Kleinbuchstaben, Zahlen und einzelne Bindestriche. Später nicht mehr änderbar.',
        ],
        'domain' => [
            'label' => 'Domain',
            'help-text' => 'Der Host, unter dem der Realm erreichbar ist, z. B. auth.example.com. Richte den DNS-Eintrag auf diesen Server und trage die Domain im Proxy der Plattform ein; angelegt wird der Realm in jedem Fall.',
        ],
        'access-token-lifetime' => [
            'label' => 'Laufzeit von Access-Tokens',
            'help-text' => 'Laufzeit neu ausgestellter Access-Tokens in Sekunden.',
        ],
        'id-token-lifetime' => [
            'label' => 'Laufzeit von ID-Tokens',
            'help-text' => 'Laufzeit neu ausgestellter ID-Tokens in Sekunden.',
        ],
        'client-credentials-lifetime' => [
            'label' => 'Laufzeit von Machine-to-Machine-Tokens',
            'help-text' => 'Laufzeit von Client-Credentials-Tokens in Sekunden.',
        ],
        'refresh-token-lifetime' => [
            'label' => 'Laufzeit von Refresh-Tokens',
            'help-text' => 'Laufzeit neu ausgestellter Refresh-Tokens in Sekunden.',
        ],
        'session-absolute-lifetime' => [
            'label' => 'Maximale Sitzungsdauer',
            'help-text' => 'Maximales Alter einer Authentifizierungssitzung in Sekunden.',
        ],
        'session-token-ttl' => [
            'label' => 'Laufzeit des Sitzungstokens',
            'help-text' => 'Laufzeit des zu einer Sitzung gehörenden Tokens in Sekunden.',
        ],
        'session-token-refresh-skew' => [
            'label' => 'Erneuerungsfenster für Sitzungstokens',
            'help-text' => 'Anzahl der Sekunden vor Ablauf, ab der ein Sitzungstoken erneuert wird.',
        ],
        'login-methods' => [
            'label' => 'Erlaubte Anmeldemethoden',
            'help-text' => 'Methoden, die dieser Realm anbietet. Eine weggelassene Methode ist gesperrt und nicht nur ausgeblendet; Registrierung und Passwort-Reset hängen an der Passwortmethode.',
        ],
        'link-by-verified-email' => [
            'label' => 'Über bestätigte E-Mail-Adresse verknüpfen',
            'help-text' => 'Eine Identität des Anbieters dem Realm-Benutzer mit derselben Adresse zuordnen, wenn der Anbieter sie als bestätigt meldet.',
        ],
        'auto-provision' => [
            'label' => 'Benutzer bei der ersten Anmeldung anlegen',
            'help-text' => 'Legt einen Realm-Benutzer an, wenn sich eine unbekannte Identität erstmals über einen Anbieter anmeldet. Nur eine bestätigte Adresse des Anbieters wird akzeptiert.',
        ],
        'email-verification-required' => [
            'label' => 'Bestätigte E-Mail-Adresse verlangen',
            'help-text' => 'Benutzer mit unbestätigter Adresse müssen sie bestätigen, bevor ihre Anmeldung abgeschlossen wird.',
        ],
        'mfa-requirement' => [
            'label' => 'Zweiter Faktor',
            'help-text' => 'Wann der Realm einen zweiten Faktor verlangt. „Immer“ führt Benutzer ohne Faktor vor Abschluss der Anmeldung durch die Einrichtung.',
        ],
        'challenge-providers' => [
            'label' => 'Verfügbare zweite Faktoren',
            'help-text' => 'Faktoren, mit denen Benutzer eine Mehrfaktor-Prüfung abschließen können.',
        ],
        'totp-secret-length' => [
            'label' => 'Länge des TOTP-Geheimnisses',
            'help-text' => 'Länge neu erzeugter Authenticator-Geheimnisse: 16 bis 64 Zeichen in Schritten von 8.',
        ],
        'totp-window' => [
            'label' => 'TOTP-Zeittoleranz',
            'help-text' => 'Akzeptierte Zeitabweichung in Schritten von 30 Sekunden.',
        ],
        'recovery-codes' => [
            'label' => 'Anzahl der Wiederherstellungscodes',
            'help-text' => 'Anzahl der Codes beim Erstellen neuer Wiederherstellungscodes.',
        ],
        'password-min-length' => [
            'label' => 'Mindestlänge des Passworts',
            'help-text' => 'Zeichen, die ein neues Passwort mindestens haben muss.',
        ],
        'password-history' => [
            'label' => 'Passwort-Historie',
            'help-text' => 'Frühere Passwörter, die ein neues Passwort nicht wiederholen darf, einschließlich des aktuellen. 0 deaktiviert die Prüfung.',
        ],
        'password-max-age-days' => [
            'label' => 'Maximales Passwortalter',
            'help-text' => 'Tage, nach denen ein Passwort als abgelaufen gilt. 0 bedeutet, dass Passwörter nie ablaufen.',
        ],
        'password-mixed-case' => [
            'label' => 'Groß- und Kleinbuchstaben verlangen',
            'help-text' => 'Ein neues Passwort muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        ],
        'password-numbers' => [
            'label' => 'Ziffer verlangen',
            'help-text' => 'Ein neues Passwort muss mindestens eine Ziffer enthalten.',
        ],
        'password-symbols' => [
            'label' => 'Sonderzeichen verlangen',
            'help-text' => 'Ein neues Passwort muss mindestens ein Sonderzeichen enthalten.',
        ],
        'password-uncompromised' => [
            'label' => 'Geleakte Passwörter ablehnen',
            'help-text' => 'Passwörter ablehnen, die in einem öffentlichen Datenleck gefunden wurden. Beim Setzen eines Passworts wird dafür die Range-API von Have I Been Pwned abgefragt.',
        ],
        'dynamic-registration' => [
            'label' => 'Dynamische Client-Registrierung erlauben',
            'help-text' => 'Anwendungen dürfen sich über den Registrierungsendpunkt des Realms anmelden.',
        ],
        'token-exchange' => [
            'label' => 'Token-Austausch erlauben',
            'help-text' => 'Clients dürfen Tokens innerhalb dieses Realms austauschen.',
        ],
        'first-party-trusted' => [
            'label' => 'Dem First-Party-Client vertrauen',
            'help-text' => 'Zustimmung für den First-Party-Client überspringen, sofern einer eingerichtet ist.',
        ],
        'allowed-redirect-schemes' => [
            'label' => 'Erlaubte eigene Weiterleitungsschemata',
            'help-text' => 'Ein Schema pro Zeile, ohne Doppelpunkt. Leer lassen, um nur HTTP und HTTPS zu erlauben.',
        ],
        'allowed-redirect-domains' => [
            'label' => 'Erlaubte Weiterleitungsdomains',
            'help-text' => 'Ein exakter Host pro Zeile. Mit * alle Hosts erlauben. Eine leere Liste erlaubt keine HTTP- oder HTTPS-Weiterleitungshosts.',
        ],
        'default-scopes' => [
            'label' => 'Standard-Scopes',
            'help-text' => 'Scopes, die jeder neue Client erhält und ohne Anfrage gewährt bekommt, ein Scope pro Zeile.',
        ],
        'optional-scopes' => [
            'label' => 'Optionale Scopes',
            'help-text' => 'Scopes, die jeder neue Client anfragen darf, ein Scope pro Zeile. Ein einzelnes * erlaubt jeden Scope aus dem Katalog.',
        ],
        'trusted-clients' => [
            'label' => 'Vertrauenswürdige Clients',
            'help-text' => 'Eine Client-Kennung aus diesem Realm pro Zeile. Diese Clients überspringen die Zustimmung.',
        ],
    ],
    'theme' => [
        'heading' => 'Design',
        'description' => 'Wie die Anmeldeseiten, die Kontoseite und die E-Mails dieses Realms aussehen. Eine leere Einstellung behält das Aussehen der Instanz.',
        'sign-in-url' => 'Anmeldeseite',
        'missing-files' => 'Diese Dateien werden von einer Einstellung genannt, sind aber noch nicht hochgeladen: :files',
        'saved' => 'Design gespeichert.',
        'values' => [
            'unset' => 'Standard',
        ],
        'appearance' => [
            'label' => 'Darstellung',
            'help-text' => 'Ein fester Modus gilt für alle Besucher und blendet die Auswahl auf der Kontoseite aus.',
            'options' => [
                'system' => 'Jeder Besucher wählt selbst',
                'light' => 'Immer hell',
                'dark' => 'Immer dunkel',
            ],
        ],
        'font-faces' => [
            'label' => 'Schriftdateien',
            'help-text' => 'Die Dateien, die die Schriftlisten nennen, als https-URL oder asset:<name>.',
            'add' => 'Schriftdatei hinzufügen',
            'count' => ':count Datei|:count Dateien',
            'family' => 'Familie',
            'file' => 'Datei',
            'weight' => 'Stärke',
            'style' => 'Stil',
        ],
        'sections' => [
            'identity' => 'Identität',
            'colors' => 'Farben',
            'shape' => 'Form und Größe',
            'typography' => 'Typografie',
            'background' => 'Hintergrund',
            'panel' => 'Formular-Panel',
            'buttons' => 'Buttons',
            'inputs' => 'Eingabefelder',
            'links' => 'Links',
            'mail' => 'E-Mail',
        ],
        'types' => [
            'color' => 'Eine Hex-Farbe oder eine Funktion wie rgba(0, 0, 0, 0.5).',
            'length' => 'Eine Länge wie 12px, 0.5rem oder 100%.',
            'font-stack' => 'Eine kommagetrennte Liste von Schriftnamen.',
            'font-shorthand' => 'Eine Stärke, eine Größe mit optionaler Zeilenhöhe und eine Schrift, zum Beispiel 700 12px/1 var(--lt-font-sans).',
            'shadow' => 'none oder kommagetrennte Schatten wie 0 1px 4px #000.',
            'gradient' => 'none oder kommagetrennte lineare oder radiale Verläufe.',
            'filter' => 'none oder Filter wie brightness(1.1).',
            'text-transform' => 'Einer der aufgeführten Werte.',
            'input-border-style' => 'Einer der aufgeführten Werte.',
            'background-fit' => 'Einer der aufgeführten Werte.',
            'file' => 'Eine https-URL oder asset:<name> für eine hochgeladene Datei.',
            'text' => 'Einfacher Text mit bis zu 255 Zeichen.',
            'email' => 'Eine E-Mail-Adresse. Ihre Domain braucht SPF und DKIM für euren Mailserver.',
        ],
        'errors' => [
            'master' => 'Der Administrations-Realm behält das Aussehen der Instanz und bekommt kein Design.',
            'unknown' => 'Es gibt keine Einstellung namens :setting.',
            'appearance' => 'Die Darstellung ist system, light oder dark.',
            'family' => 'Eine Schriftfamilie enthält Buchstaben, Ziffern, Leerzeichen, Binde- und Unterstriche.',
            'weight' => 'Eine Schriftstärke ist eine Zahl von 1 bis 1000.',
            'style' => 'Ein Schriftstil ist normal oder italic.',
        ],
        'assets' => [
            'heading' => 'Dateien',
            'description' => 'Dateien, die dieser Realm selbst ausliefert. Eine Einstellung nennt sie als asset:<name>.',
            'file' => [
                'label' => 'Datei',
                'help-text' => 'PNG, JPEG, WebP, SVG oder WOFF2, bis 5 MB.',
            ],
            'name' => [
                'label' => 'Name',
                'help-text' => 'Standard ist der eigene Name der Datei in Kleinbuchstaben.',
            ],
            'columns' => [
                'name' => 'Name',
                'type' => 'Typ',
                'size' => 'Bytes',
            ],
            'submit' => 'Hochladen',
            'uploaded' => 'Datei hochgeladen.',
            'deleted' => 'Datei gelöscht.',
            'delete' => [
                'label' => 'Löschen',
                'confirm' => [
                    'title' => 'Diese Datei löschen?',
                    'description' => 'Eine Einstellung, die sie noch nennt, wird ohne sie dargestellt.',
                ],
            ],
            'errors' => [
                'name' => 'Ein Name ist kleingeschrieben, endet auf .png, .jpg, .jpeg, .webp, .svg oder .woff2 und enthält Buchstaben, Ziffern, Punkte, Binde- und Unterstriche.',
                'size' => 'Eine Datei darf bis zu 5 MB groß sein.',
                'content' => 'Der Inhalt der Datei passt nicht zu dem Typ, den ihr Name nennt.',
            ],
        ],
    ],
];
