<?php
declare(strict_types=1);

use App\Auth\Models\User;

return [

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        /*
         * Identity is realm-local, so every user lookup carries a realm
         * constraint.
         */
        'users' => [
            'driver' => 'realm-eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

];
