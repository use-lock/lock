<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Audit\Enums\UserEventType;
use App\Audit\Models\UserEvent;
use App\Realms\Models\Realm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserEvent>
 */
final class UserEventFactory extends Factory
{
    /** @var class-string<UserEvent> */
    protected $model = UserEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(UserEventType::cases());

        return [
            'realm_id' => Realm::factory(),
            'type' => $type->value,
            'category' => $type->category()->value,
            'user_id' => null,
            'client_id' => null,
            'sid' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'failure' => $type->isFailure(),
            'context' => null,
            'occurred_at' => now(),
        ];
    }

    public function ofType(UserEventType $type): self
    {
        return $this->state([
            'type' => $type->value,
            'category' => $type->category()->value,
            'failure' => $type->isFailure(),
        ]);
    }
}
