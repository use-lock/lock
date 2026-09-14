<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceScope>
 */
final class ResourceScopeFactory extends Factory
{
    /** @var class-string<ResourceScope> */
    protected $model = ResourceScope::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory(),
            'value' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
