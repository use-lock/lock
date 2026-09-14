<?php

declare(strict_types=1);

use Tests\BrowserTestCase;
use Tests\TestCase;

/*
 * Bindings only. A helper defined here would be global, and every test file
 * shares one global function table, so reusing a name is a fatal redeclare.
 *
 * RefreshDatabase sits on Tests\TestCase: a trait on the class Pest generates
 * per file wins over an inherited method and would shadow
 * TestCase::migrateDatabases().
 */
pest()->extend(TestCase::class)
    ->in('Feature');

/*
 * The one suite that must resolve the real Vite manifest; see
 * Tests\TestCase::$stubsVite.
 */
pest()->extend(BrowserTestCase::class)
    ->in('Browser');
