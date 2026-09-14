<?php
declare(strict_types=1);

return [

    'save_missing' => [
        'enabled' => env('I18NEXT_SAVE_MISSING', false),
        'middleware' => [],
    ],

    'output' => 'nested',

    'namespaces' => true,

];
