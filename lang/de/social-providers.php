<?php
declare(strict_types=1);

return [
    'heading' => 'Anbieter',
    'description' => 'Vorgelagerte Identitätsanbieter, an die dieser Realm Anmeldungen weiterreicht. Nimm „Social-Login-Anbieter“ in die erlaubten Anmeldemethoden auf, damit sie auf der Anmeldeseite erscheinen.',
    'created' => 'Anbieter hinzugefügt.',
    'updated' => 'Anbieter aktualisiert.',
    'deleted' => 'Anbieter gelöscht.',
    'linking' => [
        'heading' => 'Kontoverknüpfung',
    ],
    'detail' => [
        'description' => ':driver-Anbieter von :realm.',
        'created-at' => 'Hinzugefügt',
        'key-fixed' => 'Teil der Redirect-URI und der darüber verknüpften Konten, daher dauerhaft festgelegt.',
        'settings' => [
            'heading' => 'Anbieter',
            'subtitle' => 'Zum Ändern eine Zeile öffnen. Schlüssel und Treiber sind dauerhaft festgelegt.',
        ],
        'secret' => [
            'set' => 'Hinterlegt',
            'missing' => 'Nicht gesetzt',
        ],
    ],
    'columns' => [
        'key' => 'Schlüssel',
        'driver' => 'Anbieter',
        'enabled' => 'Aktiv',
    ],
    'drivers' => [
        'google' => 'Google',
        'apple' => 'Apple',
        'github' => 'GitHub',
        'oidc' => 'OpenID Connect',
    ],
    'create' => [
        'label' => 'Anbieter hinzufügen',
    ],
    'delete' => [
        'label' => 'Anbieter löschen',
        'confirm' => [
            'title' => 'Diesen Anbieter löschen?',
            'description' => 'Löscht den Anbieter samt aller darüber verknüpften Konten. Benutzer, die sich nur so anmelden, verlieren ihren Zugang.',
        ],
    ],
    'fields' => [
        'key' => [
            'label' => 'Schlüssel',
            'help-text' => 'Identifiziert den Anbieter in seinen URLs und ist später nicht mehr änderbar. Seine Redirect-URI lautet :url.',
        ],
        'driver' => [
            'label' => 'Anbieter',
        ],
        'callback-url' => [
            'label' => 'Redirect-URI',
            'help-text' => 'Diese URI beim vorgelagerten Anbieter hinterlegen.',
        ],
        'issuer' => [
            'label' => 'Issuer-URL',
            'help-text' => 'Die Origin, die /.well-known/openid-configuration ausliefert, z. B. https://login.example.com.',
        ],
        'client-id' => [
            'label' => 'Client-ID',
            'help-text' => 'Der Client, den der Anbieter für diesen Realm ausgestellt hat. Bei Apple ist es die Services-ID.',
        ],
        'client-secret' => [
            'label' => 'Client-Secret',
            'help-text' => 'Das Secret zur Client-ID.',
        ],
        'team-id' => [
            'label' => 'Team-ID',
            'help-text' => 'Das Apple-Developer-Team, zu dem die Services-ID gehört.',
        ],
        'key-id' => [
            'label' => 'Key-ID',
            'help-text' => 'Die Kennung des privaten Schlüssels für „Sign in with Apple“.',
        ],
        'private-key' => [
            'label' => 'Privater Schlüssel',
            'help-text' => 'Der Inhalt der von Apple ausgestellten .p8-Datei. Apple leitet daraus das Client-Secret ab.',
        ],
        'secret' => [
            'help-text' => 'Wird verschlüsselt gespeichert und nie wieder angezeigt. Leer lassen, um das bestehende zu behalten.',
        ],
        'enabled' => [
            'label' => 'Aktiv',
            'help-text' => 'Ein deaktivierter Anbieter ist gesperrt und nicht nur ausgeblendet: seine Anmelde-URLs antworten nicht mehr.',
        ],
    ],
];
