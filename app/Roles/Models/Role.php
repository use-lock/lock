<?php
declare(strict_types=1);

namespace App\Roles\Models;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Enums\RoleKind;
use App\Tenants\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of scopes, emitted as the `roles` claim of its users' tokens.
 * Its scopes are rows of its own realm's resources, so a role can never grant
 * something outside the realm it belongs to.
 *
 * @property string $id
 * @property string $realm_id
 * @property string $name
 * @property string|null $description
 * @property RoleKind $kind
 * @property string|null $tenant_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Realm $realm
 * @property-read Tenant|null $tenant
 * @property-read Collection<int, \App\Resources\Models\Resource> $resources
 * @property-read Collection<int, ResourceScope> $scopes
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['realm_id', 'name', 'description', 'kind', 'tenant_id'])]
final class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    /**
     * @return BelongsToMany<ResourceScope, $this>
     */
    public function scopes(): BelongsToMany
    {
        return $this->belongsToMany(ResourceScope::class, 'role_scope')->withTimestamps();
    }

    /**
     * Resources the role grants every scope of, including the ones declared
     * after the grant.
     *
     * @return BelongsToMany<\App\Resources\Models\Resource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'role_resource')->withTimestamps();
    }

    /**
     * @return list<string>
     */
    public function scopeValues(): array
    {
        return array_values(array_unique($this->scopes->map(fn (ResourceScope $scope): string => $scope->value)->all()));
    }

    public function belongsToRealm(Realm $realm): bool
    {
        return $this->realm_id === $realm->id;
    }

    /**
     * Roles a user holds across the whole realm, as opposed to inside a tenant.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function realmWide(Builder $query): void
    {
        $query->where('kind', RoleKind::Realm);
    }

    /**
     * Roles the realm itself defines: its realm-wide roles and tenant presets,
     * without the roles a tenant owns.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function definedByRealm(Builder $query): void
    {
        $query->whereNull('tenant_id');
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return ['kind' => RoleKind::class];
    }
}
