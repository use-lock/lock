<?php
declare(strict_types=1);

namespace App\Audit\Concerns;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What both trails share: the realm they were recorded for and the user they
 * name. Neither relation has a foreign key behind it — an event outlives what
 * it points at — so both may resolve to null on an old row.
 */
trait IsRealmScopedEvent
{
    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forRealm(Builder $query, Realm $realm): Builder
    {
        return $query->where('realm_id', $realm->id);
    }
}
