<?php
declare(strict_types=1);

namespace Tests;

use App\Admin\Database\Seeders\GlobalRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Lattice\Support\Testing\InteractsWithLatticeComponents;
use Lock\Server\Support\Testing\InteractsWithOidc;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithLatticeComponents;
    use InteractsWithOidc;
    use RefreshDatabase {
        migrateDatabases as private baseMigrateDatabases;
    }

    /**
     * Feature tests assert Inertia props and the Lattice tree, never the Blade
     * shell's asset tags, so they run against a stub and need no frontend
     * build.
     */
    protected bool $stubsVite = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installSigningKey();

        if ($this->stubsVite) {
            $this->withoutVite();
        }
    }

    /**
     * Once per process, before the per-test transaction opens, so the data
     * migrations and the protected roles every deploy syncs live in the
     * migrated template database instead of being replayed around every test.
     */
    protected function migrateDatabases(): void
    {
        $this->baseMigrateDatabases();

        $this->artisan('migrate', ['--path' => 'database/data-migrations'])->run();
        new GlobalRoleSeeder()->run();
    }
}
