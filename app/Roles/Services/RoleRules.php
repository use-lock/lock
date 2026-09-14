<?php
declare(strict_types=1);

namespace App\Roles\Services;

use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Resources\Models\ResourceScope;
use App\Roles\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RoleRules
{
    public function __construct(private ManagementApi $api) {}

    public function ensureMutable(Role $role): void
    {
        if ($this->api->ownsRole($role)) {
            throw new LogicException('Protected roles are reconciled by app:deploy and cannot be changed here.');
        }
    }

    /**
     * @param  list<string>  $scopeIds
     * @return list<string>
     */
    public function scopeIds(Realm $realm, array $scopeIds): array
    {
        $scopeIds = array_values(array_unique($scopeIds));
        $owned = ResourceScope::query()->whereIn('id', $scopeIds)
            ->whereHas('resource', fn (Builder $query) => $query->where('realm_id', $realm->id))
            ->pluck('id')->all();

        if (count($owned) !== count($scopeIds)) {
            throw ValidationException::withMessages(['scope_ids' => [__('validation.exists', ['attribute' => 'scope_ids'])]]);
        }

        return $scopeIds;
    }
}
