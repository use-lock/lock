<?php
declare(strict_types=1);

namespace App\Roles\Actions;

use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Roles\Services\RoleRules;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRole
{
    public function __construct(private RoleRules $rules) {}

    public function handle(Role $role): void
    {
        $this->rules->ensureMutable($role);

        DB::transaction(function () use ($role): void {
            $detached = $role->users()->detach();

            $role->delete();

            Audit::record(RoleAdminEvent::RoleDeleted, $role, $role->realm, [
                'realm' => $role->realm->slug,
                'name' => $role->name,
                'users-detached' => $detached,
            ]);
        });
    }
}
