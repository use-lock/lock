<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Lock\Server\Clients\Models\Client;

it('creates a demo user without credentials or administrator privileges', function () {
    $environment = file_get_contents(app()->environmentFilePath());

    $this->seed();

    $user = User::query()->where('email', 'demo@example.com')->sole();

    expect($user->belongsToRealm(Realm::master()))->toBeTrue()
        ->and($user->can(ManagementScope::RealmsWrite))->toBeFalse()
        ->and(Client::query()->exists())->toBeFalse()
        ->and(file_get_contents(app()->environmentFilePath()))->toBe($environment);
});

it('refuses to seed demo data in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())->toThrow(RuntimeException::class, 'Demo data may only be seeded locally.')
        ->and(User::query()->exists())->toBeFalse();
});
