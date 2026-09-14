<?php
declare(strict_types=1);

namespace App\Roles\Ui\Concerns;

use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;

/**
 * A role is only reachable through its own realm: the `role` context
 * alone would let a signed reference from one realm's console address
 * another realm's role.
 */
trait ResolvesRole
{
    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }

    private function role(): Role
    {
        $role = $this->roleOrNull();

        abort_unless($role instanceof Role, 404);

        return $role;
    }

    private function roleOrNull(): ?Role
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $role = $this->contextModelOrNull('role', Role::class);

        return $realm instanceof Realm && $role instanceof Role && $role->belongsToRealm($realm) ? $role : null;
    }

    /**
     * The protected roles belong to `app:bootstrap`, so the console offers no
     * way to rename, re-scope or delete them.
     */
    private function mutableRole(): Role
    {
        $role = $this->mutableRoleOrNull();

        abort_unless($role instanceof Role, 404);

        return $role;
    }

    private function mutableRoleOrNull(): ?Role
    {
        $role = $this->roleOrNull();

        return $role instanceof Role && ! app(ManagementApi::class)->ownsRole($role) ? $role : null;
    }
}
