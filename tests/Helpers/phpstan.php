<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Pest\TestSuite;
use RuntimeException;
use Tests\TestCase;

/**
 * Pest's `test()` hands back a proxy typed as `HigherOrderTapProxy|TestCall`,
 * so a helper outside a test closure would lose every type.
 */
function runningTestCase(): TestCase
{
    $test = TestSuite::getInstance()->test;

    if (! $test instanceof TestCase) {
        throw new RuntimeException('No Tests\TestCase is currently running.');
    }

    return $test;
}

/**
 * Narrows for PHPStan without weakening the property or return type, and throws
 * if the assumption is ever wrong. Not named like an assertion: it is an
 * assumption, not an expectation.
 *
 * @template T
 *
 * @param  T  $value
 *
 * @phpstan-assert !null $value
 */
function assumeNotNull(mixed $value, string $message = 'Expected a non-null value.'): void
{
    if ($value === null) {
        throw new RuntimeException($message);
    }
}
