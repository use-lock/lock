<?php

declare(strict_types=1);

namespace App\Audit\Models;

use App\Audit\Concerns\IsRealmScopedEvent;
use App\Auth\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AdminEventFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Lang;

/**
 * What an administrator changed: the console's own trail. Carries the subject
 * it was performed on, which `user_events` has no room for; a row with no realm
 * belongs to the instance trail.
 *
 * @property string $id
 * @property string|null $realm_id
 * @property string $type
 * @property string $category
 * @property string|null $user_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $sid
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $occurred_at
 * @property-read User|null $user
 * @property-read Model|null $subject
 * @property-read string $actor_name
 * @property-read string $type_label
 *
 * @method static AdminEventFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdminEvent newModelQuery()
 * @method static Builder<static>|AdminEvent newQuery()
 * @method static Builder<static>|AdminEvent query()
 *
 * @mixin \Eloquent
 */
#[Appends(['actor_name', 'type_label'])]
#[Fillable([
    'realm_id',
    'type',
    'category',
    'user_id',
    'subject_type',
    'subject_id',
    'sid',
    'ip',
    'user_agent',
    'context',
    'occurred_at',
])]
#[WithoutTimestamps]
final class AdminEvent extends Model
{
    /** @use HasFactory<AdminEventFactory> */
    use HasFactory, HasUuids, IsRealmScopedEvent, MassPrunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<AdminEvent>  $query
     * @return Builder<AdminEvent>
     */
    #[Scope]
    protected function global(Builder $query): Builder
    {
        return $query->whereNull('realm_id');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return self::query()->where('occurred_at', '<', now()->subDays((int) config('lock.events.admin_retention_days')));
    }

    /**
     * The type is translated at read time, so a row renders in the viewer's
     * locale rather than the writer's. A type this version no longer knows
     * still lists, as its raw code.
     *
     * @return Attribute<string, never>
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::make(get: function (): string {
            $key = 'audit.admin.types.'.$this->type;

            return Lang::has($key) ? __($key) : $this->type;
        });
    }

    /**
     * A console command names itself in the context rather than acting as a
     * user, and a deleted administrator leaves their rows behind on purpose.
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

            $actor = $this->context['actor'] ?? null;

            return is_string($actor) && $actor !== '' ? __('audit.events.system-actor') : '—';
        });
    }
}
