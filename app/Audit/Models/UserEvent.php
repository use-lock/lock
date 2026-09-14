<?php

declare(strict_types=1);

namespace App\Audit\Models;

use App\Audit\Concerns\IsRealmScopedEvent;
use App\Audit\Enums\UserEventType;
use App\Auth\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\UserEventFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * What the OIDC server raises: sign-ins, MFA, consent, token and key events.
 * Separate from `admin_events` because these carry no subject, arrive at a far
 * higher volume and outlive everything they point at.
 *
 * @property string $id
 * @property string|null $realm_id
 * @property string $type
 * @property string $category
 * @property string|null $user_id
 * @property string|null $client_id
 * @property string|null $sid
 * @property string|null $ip
 * @property string|null $user_agent
 * @property bool $failure
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $user
 * @property-read string $actor_name
 * @property-read string $type_label
 *
 * @method static UserEventFactory factory($count = null, $state = [])
 * @method static Builder<static>|UserEvent newModelQuery()
 * @method static Builder<static>|UserEvent newQuery()
 * @method static Builder<static>|UserEvent query()
 *
 * @mixin \Eloquent
 */
#[Appends(['actor_name', 'type_label'])]
#[Fillable([
    'realm_id',
    'type',
    'category',
    'user_id',
    'client_id',
    'sid',
    'ip',
    'user_agent',
    'failure',
    'context',
    'occurred_at',
])]
#[WithoutTimestamps]
final class UserEvent extends Model
{
    /** @use HasFactory<UserEventFactory> */
    use HasFactory, HasUuids, IsRealmScopedEvent, MassPrunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failure' => 'boolean',
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return self::query()->where('occurred_at', '<', now()->subDays((int) config('lock.events.user_retention_days')));
    }

    /**
     * A type a package update added still lists, as its raw code.
     *
     * @return Attribute<string, never>
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::make(get: fn (): string => UserEventType::tryFrom($this->type)?->label() ?? $this->type);
    }

    /**
     * A failed login has no user to name, only the address that was tried —
     * and a deleted user leaves its rows behind on purpose.
     *
     * @return Attribute<string, never>
     */
    protected function actorName(): Attribute
    {
        return Attribute::make(get: function (): string {
            $user = $this->user;

            if ($user instanceof User) {
                return $user->name;
            }

            $username = $this->context['username'] ?? null;

            return is_string($username) && $username !== '' ? $username : '—';
        });
    }
}
