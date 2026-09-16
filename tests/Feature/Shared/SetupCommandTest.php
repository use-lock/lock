<?php
declare(strict_types=1);

use App\Auth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lock\Server\Clients\Models\Client;
use Symfony\Component\Process\Process;

use function Tests\Helpers\readEnvironmentFile;

beforeEach(function () {
    $this->environmentPath = storage_path('framework/cache').'/lock-setup-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->environmentPath);
    File::put($this->environmentPath.'/.env', '');
    app()->useEnvironmentPath($this->environmentPath);
    config([
        'lock.console_client' => ['id' => null, 'secret' => null],
        'lock.admin' => ['email' => 'root@example.test', 'password' => 'a-secure-password', 'name' => 'Root'],
        'lattice.discovery.cache_path' => $this->environmentPath.'/lattice.php',
    ]);
});

afterEach(function () {
    Artisan::call('lattice:discover-clear');
    File::deleteDirectory($this->environmentPath);
});

it('provisions local credentials once and preserves them on a repeat setup', function () {
    $appKey = config('app.key');
    $this->artisan('app:setup')->assertSuccessful();
    $environment = readEnvironmentFile(app()->environmentFilePath());
    $client = Client::query()->where('provisioning_key', 'first-party')->sole();

    expect($environment['LOCK_CONSOLE_CLIENT_ID'])->toBe($client->client_id)
        ->and($environment['LOCK_CONSOLE_CLIENT_SECRET'])->toBe($client->secret)
        ->and($client->secret)->not->toBeEmpty();

    $this->artisan('app:setup')->assertSuccessful();

    expect(readEnvironmentFile(app()->environmentFilePath()))->toBe($environment)
        ->and(config('app.key'))->toBe($appKey)
        ->and(Client::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

it('refuses production setup without changing the environment or database', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('app:setup')->assertFailed();

    expect(readEnvironmentFile(app()->environmentFilePath()))->toBeEmpty()
        ->and(Client::query()->exists())->toBeFalse()
        ->and(User::query()->exists())->toBeFalse();
});

it('sets up an empty installation without an existing key or SQLite database', function (bool $relative) {
    $database = $this->environmentPath.'/database.sqlite';
    $configuredDatabase = $relative ? substr($database, strlen(base_path()) + 1) : $database;
    File::put($this->environmentPath.'/.env', implode("\n", [
        'APP_ENV=local',
        'APP_KEY=',
        'APP_URL=http://fresh-install.test',
        'DB_CONNECTION=sqlite',
        'DB_DATABASE='.$configuredDatabase,
        'LOCK_ADMIN_EMAIL=first@example.test',
        'LOCK_ADMIN_PASSWORD=correct-horse-battery-staple',
    ]));

    $command = [PHP_BINARY, '-r', <<<'PHP'
        require $argv[2].'/vendor/autoload.php';
        $app = require $argv[2].'/bootstrap/app.php';
        $app->useEnvironmentPath($argv[1]);
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        config(['lattice.discovery.cache_path' => $argv[1].'/lattice.php']);
        exit($kernel->call($argv[3], ['--no-interaction' => true]));
        PHP, $this->environmentPath, base_path(), 'app:setup'];
    $process = new Process($command, sys_get_temp_dir(), [
        'APP_ENV' => false, 'APP_KEY' => false, 'APP_URL' => false,
        'DB_CONNECTION' => false, 'DB_DATABASE' => false,
        'LOCK_ADMIN_EMAIL' => false, 'LOCK_ADMIN_PASSWORD' => false, 'LOCK_ADMIN_NAME' => false,
        'LOCK_CONSOLE_CLIENT_ID' => false, 'LOCK_CONSOLE_CLIENT_SECRET' => false,
    ]);
    $process->mustRun();
    $command[array_key_last($command)] = 'app:deploy';
    $deploy = new Process($command, sys_get_temp_dir(), $process->getEnv());
    $deploy->mustRun();

    $environment = readEnvironmentFile($this->environmentPath.'/.env');
    $databaseConnection = new PDO('sqlite:'.$database);
    $users = $databaseConnection->query('SELECT email FROM users');
    $keys = $databaseConnection->query('SELECT count(*) FROM oidc_signing_keys');

    if ($users === false || $keys === false) {
        throw new RuntimeException('Cannot inspect the provisioned database.');
    }

    expect($environment['APP_KEY'])->toStartWith('base64:')
        ->and($environment['LOCK_CONSOLE_CLIENT_SECRET'])->not->toBeEmpty()
        ->and($users->fetchColumn())->toBe('first@example.test')
        ->and($keys->fetchColumn())->toBe(1)
        ->and(file_exists($database.'.deploy.lock'))->toBeTrue();
})->with(['absolute database path' => false, 'relative database path' => true]);
