<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withComposerBased(laravel: true)
    ->withSets([
        PestSetList::CODING_STYLE,
        LevelSetList::UP_TO_PHP_84,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ])
    ->withSkip([
        AppToResolveRector::class,
    ]);
