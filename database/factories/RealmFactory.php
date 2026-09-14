<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Realms\Models\Realm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lock\Server\SigningKeys\SigningKeyGenerator;
use Lock\Server\SigningKeys\SigningKeyStore;

/**
 * @extends Factory<Realm>
 */
final class RealmFactory extends Factory
{
    /** @var class-string<Realm> */
    protected $model = Realm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            // Browsers resolve every `*.localhost` to the loopback address, so
            // a fixture realm is reachable in browser tests without DNS.
            'domain' => fn (array $attributes): string => $attributes['slug'].'.localhost',
        ];
    }

    /**
     * A realm can neither mint a token nor serve a JWKS without a keypair of
     * its own.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Realm $realm): void {
            $realm->runAsCurrent(
                fn () => app(SigningKeyStore::class)->rotate(app(SigningKeyGenerator::class)->generate()),
            );
        });
    }
}
