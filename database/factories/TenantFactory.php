<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Realms\Models\Realm;
use App\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /** @var class-string<Tenant> */
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'realm_id' => Realm::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'personal' => false,
        ];
    }

    public function personal(): self
    {
        return $this->state(['personal' => true]);
    }
}
