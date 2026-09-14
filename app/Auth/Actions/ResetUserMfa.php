<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Support\Facades\DB;

final class ResetUserMfa
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->totpFactors()->delete();
            $user->recoveryCodes()->delete();
            $user->passkeys()->delete();
        });
    }
}
