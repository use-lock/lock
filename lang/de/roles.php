<?php
declare(strict_types=1);

return [
    'heading' => 'Rollen',
    'description' => 'Realm-Rollen von :realm, ausgegeben als roles-Claim in jedem ID- und Access-Token.',
    'created' => 'Rolle angelegt.',
    'updated' => 'Rolle aktualisiert.',
    'deleted' => 'Rolle gelöscht.',
    'columns' => [
        'name' => 'Name',
        'description' => 'Beschreibung',
        'users' => 'Benutzer',
    ],
    'create' => [
        'label' => 'Rolle anlegen',
        'description' => 'Eine Rolle anlegen und ihre Scopes in :realm auswählen.',
    ],
    'detail' => [
        'description' => 'Rolle von :realm.',
        'created-at' => 'Erstellt',
        'settings' => ['heading' => 'Einstellungen'],
        'scopes' => ['empty' => 'Dieser Rolle sind keine Scopes zugewiesen.'],
        'protected' => [
            'label' => 'Systemrolle',
            'description' => 'Diese Rolle wird von Lock verwaltet und kann nicht geändert oder gelöscht werden.',
        ],
    ],
    'edit' => [
        'label' => 'Bearbeiten',
    ],
    'delete' => [
        'label' => 'Löschen',
        'confirm' => [
            'title' => 'Diese Rolle löschen?',
            'description' => 'Die Rolle wird allen Benutzern entzogen, die sie tragen. Bereits ausgestellte Tokens behalten sie bis zum Ablauf.',
        ],
    ],
    'fields' => [
        'name' => [
            'label' => 'Name',
            'help-text' => 'Der Wert, den Relying Parties im roles-Claim erhalten, zum Beispiel admin oder member.',
        ],
        'description' => [
            'label' => 'Beschreibung',
        ],
        'scopes' => [
            'label' => 'Scopes',
            'help-text' => 'Was die Rolle gewährt. Ein Scope gehört zu einer Resource dieses Realms; eine Rolle, die schreiben darf, braucht auch den passenden Lese-Scope.',
        ],
    ],
    'validation' => [
        'name-format' => 'Der Name darf nur Buchstaben, Ziffern und die Zeichen _ . : - enthalten und muss mit einem Buchstaben oder einer Ziffer beginnen.',
    ],
];
