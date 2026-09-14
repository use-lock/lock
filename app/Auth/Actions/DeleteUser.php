<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use App\Shared\Auth\Contracts\DeletesUser;
use App\Shared\Auth\Events\UserDeleting;
use Illuminate\Support\Facades\DB;

/**
 * Relying parties are told first, while the sessions still exist to build a
 * logout token from.
 */
final readonly class DeleteUser implements DeletesUser
{
    public function __construct(
        private EndUserSessions $endSessions,
    ) {}

    public function handle(User $user): void
    {
        $this->endSessions->handle($user);

        DB::transaction(function () use ($user): void {
            event(new UserDeleting($user));
            $user->notifications()->delete();
            $user->delete();
        });
    }
}
