<?php
declare(strict_types=1);

namespace App\Realms\Actions;

use App\Auth\Models\User;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use App\Shared\Auth\Contracts\DeletesUser;
use App\Shared\Realms\Events\RealmDeleting;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRealm
{
    public function __construct(private DeletesUser $deleteUser) {}

    /** The row goes for good, so a deleted realm's slug is free again. */
    public function handle(Realm $realm): void
    {
        DB::transaction(function () use ($realm): void {
            $realm->roles()->delete();
            $realm->users()->eachById(function (User $user): void {
                $this->deleteUser->handle($user);
            });

            event(new RealmDeleting($realm->slug));

            $realm->delete();

            Audit::record(RealmAdminEvent::RealmDeleted, $realm, context: ['name' => $realm->name, 'slug' => $realm->slug]);
        });
    }
}
