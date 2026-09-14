<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Demo data may only be seeded locally.');
        }

        User::factory()->for(Realm::master())->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);
    }
}
