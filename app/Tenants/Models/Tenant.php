<?php

declare(strict_types=1);

namespace App\Tenants\Models;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of users inside a realm with tenancy, such as a customer workspace.
 * The realm's tenant presets apply in it next to the roles it owns itself.
 *
 * @property string $id
 * @property string $realm_id
 * @property string $name
 * @property string $slug
 * @property bool $personal
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Realm $realm
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['realm_id', 'name', 'slug', 'personal'])]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_members')->withTimestamps();
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['personal' => 'boolean'];
    }
}
