<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Models\ResourceScope;
use App\Roles\Models\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Lock\Server\Credentials\Concerns\HasAuthenticationFactors;
use Lock\Server\Shared\Authentication\RealmUser;
use Lock\Server\Tokens\Concerns\HasAccessTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $blocked_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $realm_id
 * @property string|null $locale
 * @property string|null $timezone
 * @property-read Realm $realm
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRealmId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'email', 'password', 'realm_id', 'locale', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser, RealmUser
{
    /** @use HasFactory<UserFactory> */
    use HasAccessTokens, HasAuthenticationFactors, HasFactory, HasUuids, Notifiable;

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return array_values($this->roles()
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all());
    }

    public function belongsToRealm(Realm $realm): bool
    {
        return $this->realm_id === $realm->id;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function hasSecondFactor(): bool
    {
        return $this->totpFactors()->whereNotNull('confirmed_at')->exists() || $this->passkeys()->exists();
    }

    public function hasManagementScope(ManagementScope $scope): bool
    {
        return $this->managementScopes()->contains($scope->value);
    }

    /**
     * Whether the person holds every one of them — the same subset rule
     * `CheckScopes` applies to a token, so a gate and an API route read a
     * scope set the same way.
     *
     * @param  list<ManagementScope>  $scopes
     */
    public function hasManagementScopes(array $scopes): bool
    {
        $granted = $this->managementScopes();

        return array_all($scopes, fn ($scope) => $granted->contains($scope->value));
    }

    /**
     * The management API scopes this person's roles grant. Matching the
     * resource and not just the value matters: any realm may declare a
     * resource whose scope reads `realms:write`, and only the master realm's
     * matching Admin or Management API resource means the console.
     *
     * Loaded rather than queried: a users table ran over three hundred queries
     * when every gate check re-read the pivot. `loadMissing` walks the whole
     * chain, because `refresh()` restores `roles` without its nested
     * relations. `refresh()` picks up a role change made after a check.
     *
     * @return SupportCollection<int, string>
     */
    public function managementScopes(): SupportCollection
    {
        if (! $this->realm->isMaster()) {
            return new SupportCollection;
        }

        $this->loadMissing(['roles.scopes.resource']);

        return $this->roles
            ->filter(fn (Role $role): bool => $role->realm_id === $this->realm_id)
            ->flatMap(fn (Role $role): array => $role->scopes->all())
            ->filter(fn (ResourceScope $scope): bool => $scope->resource->realm_id === $this->realm_id
                && ManagementScope::tryFrom($scope->value)?->apiResource()->value === $scope->resource->identifier)
            ->map(fn (ResourceScope $scope): string => $scope->value)
            ->unique()
            ->values();
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
