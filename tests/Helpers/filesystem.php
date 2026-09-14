<?php

declare(strict_types=1);

namespace Tests\Helpers;

use RuntimeException;

/**
 * Fails loudly when the file cannot be parsed instead of letting the assertions
 * run against `false`.
 *
 * @return array<string, mixed>
 */
function readEnvironmentFile(string $path): array
{
    $environment = parse_ini_file($path, false, INI_SCANNER_RAW);

    if ($environment === false) {
        throw new RuntimeException("Unable to parse the environment file at {$path}.");
    }

    return $environment;
}

/**
 * @return list<string>
 */
function globPaths(string $pattern, int $flags = 0): array
{
    $paths = glob($pattern, $flags);

    if ($paths === false) {
        throw new RuntimeException("Unable to expand the glob pattern {$pattern}.");
    }

    return $paths;
}
