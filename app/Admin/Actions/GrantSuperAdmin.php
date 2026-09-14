<?php
declare(strict_types=1);

namespace App\Admin\Actions;

use App\Admin\ManagementRoles;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GrantSuperAdmin
{
    /**
     * @param  string  $actor  what asked for the grant, recorded on the audit entry
     * @return bool whether the role was newly granted
     */
    public function handle(User $user, string $actor): bool
    {
        return DB::transaction(function () use ($user, $actor): bool {
            $role = $user->realm->roles()->where('name', ManagementRoles::SUPER_ADMIN)->first()
                ?? throw new RuntimeException('The Super Admin role is not seeded. Run `php artisan app:bootstrap` first.');

            if ($user->roles()->where('roles.id', $role->id)->exists()) {
                return false;
            }

            $user->roles()->syncWithoutDetaching([$role->id]);

            Audit::record(
                UserAdminEvent::SuperAdminGranted,
                $user,
                context: ['actor' => $actor, 'added' => [$role->name], 'removed' => []],
            );

            return true;
        });
    }
}
