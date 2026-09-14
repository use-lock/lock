<?php
declare(strict_types=1);

namespace App\Roles\Actions;

use App\Realms\Models\Realm;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Roles\Services\RoleRules;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;

final readonly class CreateRole
{
    public function __construct(private RoleRules $rules) {}

    /**
     * @param  list<string>  $scopeIds
     */
    public function handle(Realm $realm, string $name, ?string $description, array $scopeIds): Role
    {
        return DB::transaction(function () use ($realm, $name, $description, $scopeIds): Role {
            $role = $realm->roles()->create([
                'name' => $name,
                'description' => $description,
            ]);

            $role->scopes()->sync($this->rules->scopeIds($realm, $scopeIds));

            Audit::record(RoleAdminEvent::RoleCreated, $role, $realm, [
                'realm' => $realm->slug,
                'name' => $role->name,
                'scopes' => $role->load('scopes')->scopeValues(),
            ]);

            return $role;
        });
    }
}
