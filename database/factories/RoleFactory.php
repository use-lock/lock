<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    /** @var class-string<Role> */
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'realm_id' => Realm::factory(),
            'name' => Str::slug(fake()->unique()->jobTitle()),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
