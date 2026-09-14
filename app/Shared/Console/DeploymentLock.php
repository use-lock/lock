<?php
declare(strict_types=1);

namespace App\Shared\Console;

use Closure;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

final class DeploymentLock
{
    public function run(Closure $deploy): ?int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $database = $connection->scalar("SELECT file FROM pragma_database_list WHERE name = 'main'");

            if (! is_string($database)) {
                throw new RuntimeException('Cannot resolve the SQLite database file.');
            }

            $path = $database === ''
                ? storage_path('framework/cache/deploy-'.getmypid().'.lock')
                : $database.'.deploy.lock';
            $handle = fopen($path, 'c');

            if ($handle === false) {
                throw new RuntimeException('Cannot open the deployment lock.');
            }

            try {
                return flock($handle, LOCK_EX | LOCK_NB) ? $deploy() : null;
            } finally {
                fclose($handle);
            }
        }

        [$acquire, $release, $key] = match ($driver) {
            'mysql', 'mariadb' => ['SELECT GET_LOCK(?, 0)', 'SELECT RELEASE_LOCK(?)', 'lock-deploy-'.sha1($connection->getDatabaseName())],
            'pgsql' => ['SELECT pg_try_advisory_lock(?)', 'SELECT pg_advisory_unlock(?)', 1279476555],
            default => throw new RuntimeException("Deployment locking is unsupported for [{$driver}]."),
        };

        // A dedicated session keeps the lock across migration transactions and reconnects.
        DB::connectUsing('lock-deployment', $connection->getConfig());
        $pdo = DB::connection('lock-deployment')->getPdo();

        try {
            $statement = $pdo->prepare($acquire);
            $statement->execute([$key]);

            if (! in_array($statement->fetchColumn(), [true, 1, '1', 't'], true)) {
                return null;
            }

            try {
                return $deploy();
            } finally {
                $this->release($pdo, $release, $key);
            }
        } finally {
            DB::purge('lock-deployment');
        }
    }

    private function release(PDO $pdo, string $query, int|string $key): void
    {
        $statement = $pdo->prepare($query);
        $statement->execute([$key]);
    }
}
