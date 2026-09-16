<?php
declare(strict_types=1);

use App\Admin\ManagementRoles;
use App\Realms\Models\Realm;
use App\Shared\Console\DeploymentLock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lattice\Core\Discovery\DiscoveryManifest;
use Lock\Server\Clients\Models\Client;
use Lock\Server\SigningKeys\Models\SigningKey;

beforeEach(function () {
    config(['lattice.discovery.cache_path' => $this->app->bootstrapPath('cache/lattice-deploy-test-'.getmypid().'.php')]);
});

it('runs every deploy step and leaves the app state primed', function () {
    Realm::factory()->create(['slug' => 'partners']);
    SigningKey::query()->delete();
    Realm::master()->roles()->delete();
    config([
        'lock.console_client' => ['id' => 'lock-console', 'secret' => 'console-secret'],
        'lock.admin' => ['email' => 'root@lock.test', 'name' => 'Root', 'password' => 'CorrectHorse42'],
    ]);

    try {
        $this->artisan('app:deploy')->assertSuccessful();

        expect(SigningKey::query()->whereNull('retired_at')->pluck('realm')->all())
            ->toEqualCanonicalizing(['admin', 'partners'])
            ->and(Realm::master()->roles()->pluck('name')->all())
            ->toEqualCanonicalizing(ManagementRoles::names())
            ->and(Client::query()->where('client_id', 'lock-console')->exists())->toBeTrue()
            ->and(Realm::master()->users()->where('email', 'root@lock.test')->exists())->toBeTrue()
            ->and(app(DiscoveryManifest::class)->isCached())->toBeTrue();
    } finally {
        Artisan::call('lattice:discover-clear');
    }
});

it('runs the first deploy with the database cache store before its lock table exists', function () {
    config(['cache.default' => 'database']);
    Schema::drop('cache');
    Schema::drop('cache_locks');
    DB::table('migrations')->where('migration', '0001_01_01_000001_create_cache_table')->delete();

    try {
        $this->artisan('app:deploy')->assertSuccessful();

        expect(Schema::hasTable('cache_locks'))->toBeTrue();
    } finally {
        Artisan::call('lattice:discover-clear');
    }
});

it('refuses an overlapping deploy before running any dependent step', function () {
    SigningKey::query()->delete();
    Schema::drop('cache');
    Schema::drop('cache_locks');

    app(DeploymentLock::class)->run(function (): int {
        $this->artisan('app:deploy')->expectsOutputToContain('Another deployment is running')->assertFailed();

        return 0;
    });

    expect(SigningKey::query()->exists())->toBeFalse()
        ->and(Schema::hasTable('cache_locks'))->toBeFalse();
});

it('stops on a failed bootstrap and releases the deployment lock for a retry', function () {
    config([
        'lock.admin' => ['email' => 'root@example.test', 'name' => 'Root', 'password' => 'short'],
    ]);

    try {
        $this->artisan('app:deploy')->assertFailed();

        expect(app(DiscoveryManifest::class)->isCached())->toBeFalse();

        config(['lock.admin.password' => 'correct-horse-battery-staple']);

        $this->artisan('app:deploy')->assertSuccessful();

        expect(app(DiscoveryManifest::class)->isCached())->toBeTrue();
    } finally {
        Artisan::call('lattice:discover-clear');
    }
});
