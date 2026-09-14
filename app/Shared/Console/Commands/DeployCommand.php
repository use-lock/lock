<?php

declare(strict_types=1);

namespace App\Shared\Console\Commands;

use App\Realms\Models\Realm;
use App\Shared\Console\DeploymentLock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Run the deploy steps every release needs: migrations, data migrations, signing keys, environment bootstrap, lattice discovery cache, OpenAPI document cache.')]
#[Signature('app:deploy')]
final class DeployCommand extends Command
{
    public function handle(DeploymentLock $lock): int
    {
        $result = $lock->run($this->deploy(...));

        if ($result === null) {
            $this->components->error('Another deployment is running. Retry after it finishes.');

            return self::FAILURE;
        }

        return $result;
    }

    private function deploy(): int
    {
        $steps = [
            ['migrate', fn (): int => $this->call('migrate', ['--force' => true])],
            ['migrate --path=database/data-migrations', fn (): int => $this->call('migrate', ['--force' => true, '--path' => 'database/data-migrations'])],
            ['oidc:rotate-keys --if-missing', $this->generateMissingSigningKeys(...)],
            ['app:bootstrap', fn (): int => $this->call('app:bootstrap')],
            ['lattice:discover-cache', fn (): int => $this->call('lattice:discover-cache')],
            ['scramble:cache', fn (): int => $this->call('scramble:cache')],
        ];

        foreach ($steps as [$label, $step]) {
            if ($step() !== self::SUCCESS) {
                $this->components->error("Deploy step [{$label}] failed.");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * The command only sees the current realm, hence the loop. It runs after
     * the data migrations, which create the master realm.
     */
    private function generateMissingSigningKeys(): int
    {
        foreach (Realm::query()->cursor() as $realm) {
            $failed = $realm->runAsCurrent(
                fn (): bool => $this->call('oidc:rotate-keys', ['--if-missing' => true]) !== self::SUCCESS,
            );

            if ($failed === true) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
