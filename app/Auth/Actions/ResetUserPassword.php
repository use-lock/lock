<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Validator;
use Lock\Server\Authentication\Contracts\ResetUserPassword as ResetUserPasswordContract;

final class ResetUserPassword implements ResetUserPasswordContract
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(CanResetPassword $user, array $input): void
    {
        abort_unless($user instanceof User, 422);

        Validator::make($input, [
            'password' => ['required', 'string', 'confirmed'],
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
