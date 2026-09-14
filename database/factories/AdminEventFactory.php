<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Audit\Models\AdminEvent;
use App\Audit\Support\AdminEventTypes;
use App\Realms\Models\Realm;
use App\Shared\Audit\Contracts\AdminEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminEvent>
 */
final class AdminEventFactory extends Factory
{
    /** @var class-string<AdminEvent> */
    protected $model = AdminEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'realm_id' => Realm::factory(),
            ...$this->attributesOf(fake()->randomElement(AdminEventTypes::casesOf())),
            'user_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'sid' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'context' => null,
            'occurred_at' => now(),
        ];
    }

    public function ofType(AdminEventType $type): self
    {
        return $this->state($this->attributesOf($type));
    }

    /**
     * @return array<string, string>
     */
    private function attributesOf(AdminEventType $type): array
    {
        return [
            'type' => $type->type(),
            'category' => $type->category()->value,
        ];
    }
}
