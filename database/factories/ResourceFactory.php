<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Resources\Models\Resource>
 */
final class ResourceFactory extends Factory
{
    /** @var class-string<\App\Resources\Models\Resource> */
    protected $model = Resource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'realm_id' => Realm::factory(),
            'identifier' => Str::slug((string) $name),
            'name' => Str::title((string) $name),
        ];
    }

    public function absolute(string $identifier = 'https://api.example.test'): static
    {
        return $this->state(['identifier' => $identifier]);
    }
}
