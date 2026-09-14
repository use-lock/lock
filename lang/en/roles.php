<?php
declare(strict_types=1);

return [
    'heading' => 'Roles',
    'description' => 'Realm roles of :realm, issued as the roles claim of every ID and access token.',
    'created' => 'Role created.',
    'updated' => 'Role updated.',
    'deleted' => 'Role deleted.',
    'columns' => [
        'name' => 'Name',
        'description' => 'Description',
        'users' => 'Users',
    ],
    'create' => [
        'label' => 'Create role',
        'description' => 'Create a role and choose its scopes in :realm.',
    ],
    'detail' => [
        'description' => 'Role of :realm.',
        'created-at' => 'Created',
        'settings' => ['heading' => 'Settings'],
        'scopes' => ['empty' => 'No scopes assigned to this role.'],
        'protected' => [
            'label' => 'System role',
            'description' => 'This role is managed by Lock and cannot be changed or deleted.',
        ],
    ],
    'edit' => [
        'label' => 'Edit',
    ],
    'delete' => [
        'label' => 'Delete',
        'confirm' => [
            'title' => 'Delete this role?',
            'description' => 'The role is removed from every user that holds it. Tokens already issued keep it until they expire.',
        ],
    ],
    'fields' => [
        'name' => [
            'label' => 'Name',
            'help-text' => 'The value relying parties receive in the roles claim, for example admin or member.',
        ],
        'description' => [
            'label' => 'Description',
        ],
        'scopes' => [
            'label' => 'Scopes',
            'help-text' => 'What the role grants. A scope belongs to a resource of this realm; a role that may write also needs the matching read scope.',
        ],
    ],
    'validation' => [
        'name-format' => 'The name may only contain letters, digits, and the characters _ . : - and has to start with a letter or digit.',
    ],
];
