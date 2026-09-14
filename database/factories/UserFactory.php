<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lock\Server\Credentials\RecoveryCodeProvider;
use Lock\Server\Credentials\TotpFactorProvider;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /** @var class-string<User> */
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A fresh realm per user unless the caller names one, so tests stay
            // isolated.
            'realm_id' => Realm::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => null,
            'timezone' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(TotpFactorProvider::class)->enroll($user);
            $user->totpFactors()->update(['confirmed_at' => now()]);
            app(RecoveryCodeProvider::class)->generate($user);
        });
    }
}
