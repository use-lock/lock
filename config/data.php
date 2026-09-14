<?php
declare(strict_types=1);

return [
    'wrap' => 'data',
    'structure_caching' => [
        'enabled' => true,
        'directories' => [app_path()],
        'cache' => [
            'store' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),
            'prefix' => 'laravel-data',
            'duration' => null,
        ],
        'reflection_discovery' => [
            'enabled' => true,
            'base_path' => base_path(),
            'root_namespace' => null,
        ],
    ],
];
