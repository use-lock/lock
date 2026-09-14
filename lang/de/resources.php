<?php
declare(strict_types=1);

return [
    'heading' => 'Resources',
    'description' => 'Resource-Server von :realm und die Scopes, die sie besitzen.',
    'created' => 'Resource angelegt.',
    'updated' => 'Resource geändert.',
    'deleted' => 'Resource gelöscht.',
    'columns' => [
        'name' => 'Name',
        'identifier' => 'Identifier',
        'scopes' => 'Scopes',
    ],
    'create' => [
        'label' => 'Resource hinzufügen',
    ],
    'edit' => [
        'label' => 'Resource bearbeiten',
    ],
    'delete' => [
        'label' => 'Resource löschen',
        'confirm' => [
            'title' => 'Diese Resource löschen?',
            'description' => 'Löscht die Resource samt allen Scopes. Tokens können dafür nicht mehr angefordert werden.',
        ],
    ],
    'fields' => [
        'identifier' => [
            'label' => 'Identifier',
            'help-text' => 'Ein Pfad unterhalb des Realm-Issuers (api) wird als Protected-Resource-Metadata veröffentlicht; eine absolute URI benennt einen extern betriebenen Resource-Server.',
        ],
        'name' => [
            'label' => 'Name',
        ],
    ],
    'validation' => [
        'identifier-format' => 'Der Identifier muss ein Pfadsegment wie api oder v1/orders sein oder eine absolute URI.',
        'identifier-uri' => 'Der Identifier muss eine absolute URI mit Host und ohne Fragment sein.',
    ],
    'detail' => [
        'scopes' => ['heading' => 'Scopes'],
        'description' => 'Resource von :realm.',
        'audience' => 'Audience',
        'settings' => [
            'heading' => 'Resource',
            'subtitle' => 'Die Audience, die ein Client über den resource-Parameter anfordert.',
        ],
    ],
    'scopes' => [
        'heading' => 'Scopes',
        'created' => 'Scope angelegt.',
        'updated' => 'Scope geändert.',
        'deleted' => 'Scope gelöscht.',
        'columns' => [
            'value' => 'Scope',
            'description' => 'Beschreibung',
        ],
        'create' => [
            'label' => 'Scope hinzufügen',
        ],
        'edit' => [
            'label' => 'Scope bearbeiten',
        ],
        'delete' => [
            'label' => 'Scope löschen',
            'confirm' => [
                'title' => 'Diesen Scope löschen?',
                'description' => 'Tokens können damit nicht mehr ausgestellt werden. Bestehende Tokens behalten ihn bis zum Ablauf.',
            ],
        ],
        'fields' => [
            'value' => [
                'label' => 'Scope',
                'help-text' => 'Der Wert, den ein Client anfordert. Eindeutig innerhalb dieser Resource; eine andere Resource darf denselben tragen.',
            ],
            'description' => [
                'label' => 'Beschreibung',
                'help-text' => 'Wird auf dem Consent-Screen gezeigt. Ohne Beschreibung spricht der Scope für sich.',
            ],
        ],
        'validation' => [
            'value-format' => 'Ein Scope darf keine Leerzeichen enthalten. Erlaubt sind Buchstaben, Ziffern, Punkte, Doppelpunkte, Schrägstriche, Binde- und Unterstriche.',
        ],
    ],
];
