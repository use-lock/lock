<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Shared\Audit\Audit;

final class ResendUserVerification
{
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->realm->runAsCurrent(fn () => $user->sendEmailVerificationNotification());
        Audit::record(UserAdminEvent::VerificationResent, $user);

        return true;
    }
}
