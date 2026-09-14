<?php
declare(strict_types=1);

return [
    'heading' => 'Resources',
    'description' => 'Resource servers of :realm and the scopes they own.',
    'created' => 'Resource created.',
    'updated' => 'Resource updated.',
    'deleted' => 'Resource deleted.',
    'columns' => [
        'name' => 'Name',
        'identifier' => 'Identifier',
        'scopes' => 'Scopes',
    ],
    'create' => [
        'label' => 'Add resource',
    ],
    'edit' => [
        'label' => 'Edit resource',
    ],
    'delete' => [
        'label' => 'Delete resource',
        'confirm' => [
            'title' => 'Delete this resource?',
            'description' => 'Deletes the resource and every scope it owns. Tokens can no longer be requested for it.',
        ],
    ],
    'fields' => [
        'identifier' => [
            'label' => 'Identifier',
            'help-text' => 'A path below the realm issuer (api) is published as protected resource metadata; an absolute URI names a resource server hosted elsewhere.',
        ],
        'name' => [
            'label' => 'Name',
        ],
    ],
    'validation' => [
        'identifier-format' => 'The identifier must be a path segment such as api or v1/orders, or an absolute URI.',
        'identifier-uri' => 'The identifier must be an absolute URI with a host and no fragment.',
    ],
    'detail' => [
        'scopes' => ['heading' => 'Scopes'],
        'description' => 'Resource of :realm.',
        'audience' => 'Audience',
        'settings' => [
            'heading' => 'Resource',
            'subtitle' => 'The audience a client asks for with the resource parameter.',
        ],
    ],
    'scopes' => [
        'heading' => 'Scopes',
        'created' => 'Scope created.',
        'updated' => 'Scope updated.',
        'deleted' => 'Scope deleted.',
        'columns' => [
            'value' => 'Scope',
            'description' => 'Description',
        ],
        'create' => [
            'label' => 'Add scope',
        ],
        'edit' => [
            'label' => 'Edit scope',
        ],
        'delete' => [
            'label' => 'Delete scope',
            'confirm' => [
                'title' => 'Delete this scope?',
                'description' => 'Tokens can no longer be issued with it. Existing tokens keep it until they expire.',
            ],
        ],
        'fields' => [
            'value' => [
                'label' => 'Scope',
                'help-text' => 'The value a client requests. Unique within this resource; another resource may use the same one.',
            ],
            'description' => [
                'label' => 'Description',
                'help-text' => 'Shown on the consent screen. Without one the scope speaks for itself.',
            ],
        ],
        'validation' => [
            'value-format' => 'A scope may not contain spaces. Use letters, digits, dots, colons, slashes, dashes or underscores.',
        ],
    ],
];
