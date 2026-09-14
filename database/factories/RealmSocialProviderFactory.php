<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Realms\Enums\SocialProviderDriver;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealmSocialProvider>
 */
final class RealmSocialProviderFactory extends Factory
{
    /** @var class-string<RealmSocialProvider> */
    protected $model = RealmSocialProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'realm_id' => Realm::factory(),
            'key' => 'google',
            'driver' => SocialProviderDriver::Google,
            'enabled' => true,
            'config' => [
                'client_id' => fake()->uuid().'.apps.googleusercontent.com',
                'client_secret' => fake()->sha1(),
            ],
        ];
    }

    public function oidc(string $key = 'acme', string $issuer = 'https://idp.acme.test'): static
    {
        return $this->state([
            'key' => $key,
            'driver' => SocialProviderDriver::Oidc,
            'config' => [
                'issuer' => $issuer,
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha1(),
            ],
        ]);
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }
}
