<?php
declare(strict_types=1);

namespace App\Shared\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lock\Server\Support\Setup\EnvironmentFile;

#[Description('Prepare local console credentials and run the application deployment.')]
#[Signature('app:setup')]
final class SetupCommand extends Command
{
    public function handle(EnvironmentFile $environment): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('Local setup requires APP_ENV=local. Deploy with app:deploy instead.');

            return self::FAILURE;
        }

        if (blank(config('app.key')) && $this->call('key:generate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (config('database.default') === 'sqlite') {
            $database = config('database.connections.sqlite.database');

            if (is_string($database) && $database !== ':memory:' && ! str_starts_with($database, 'file:')
                && ! str_contains($database, '?mode=memory') && ! str_contains($database, '&mode=memory')) {
                $directory = realpath(dirname($database)) ?: realpath(base_path(dirname($database)));

                if ($directory === false) {
                    $this->components->error('The SQLite database directory does not exist.');

                    return self::FAILURE;
                }

                $database = realpath($database) ?: realpath(base_path($database)) ?: $directory.DIRECTORY_SEPARATOR.basename($database);

                if (! file_exists($database) && ! touch($database)) {
                    $this->components->error('Cannot create the local SQLite database.');

                    return self::FAILURE;
                }

                config(['database.connections.sqlite.database' => $database]);
            }
        }

        $credentials = [
            'id' => config('lock.console_client.id') ?: 'lock-console',
            'secret' => config('lock.console_client.secret') ?: Str::random(40),
        ];

        $environment->write([
            'LOCK_CONSOLE_CLIENT_ID' => $credentials['id'],
            'LOCK_CONSOLE_CLIENT_SECRET' => $credentials['secret'],
        ]);
        config(['lock.console_client' => $credentials]);

        return $this->call('app:deploy');
    }
}
