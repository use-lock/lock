<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Auth\Models\User;
use App\Tenants\Models\Membership;
use App\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    /** @var class-string<Membership> */
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
        ];
    }
}
