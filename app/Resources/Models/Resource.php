<?php
declare(strict_types=1);

namespace App\Resources\Models;

use App\Realms\Models\Realm;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unscoped like Role: the console reads a realm's resources while the
 * administrator's own realm is current.
 *
 * `resource` is a PHP type keyword, so Pint's phpdoc_types fixer lowercases a
 * bare `Resource` in any docblock and PHPStan then rejects the case. Write the
 * class fully qualified in generics and @property tags.
 *
 * @property string $id
 * @property string $realm_id
 * @property string $identifier
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Realm $realm
 * @property-read Collection<int, ResourceScope> $scopes
 * @property-read int|null $scopes_count
 *
 * @method static \Database\Factories\ResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['realm_id', 'identifier', 'name'])]
final class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return HasMany<ResourceScope, $this>
     */
    public function scopes(): HasMany
    {
        return $this->hasMany(ResourceScope::class);
    }

    public function belongsToRealm(Realm $realm): bool
    {
        return $this->realm_id === $realm->id;
    }

    public function isAbsolute(): bool
    {
        return parse_url($this->identifier, PHP_URL_SCHEME) !== null;
    }
}
