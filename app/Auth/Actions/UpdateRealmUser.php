<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;

final class UpdateRealmUser
{
    public function handle(User $user, string $name, string $email, bool $verified): void
    {
        DB::transaction(function () use ($user, $name, $email, $verified): void {
            $before = ['name' => $user->name, 'email' => $user->email, 'verified' => $user->hasVerifiedEmail()];

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $verified ? ($user->email_verified_at ?? now()) : null,
            ])->save();

            $after = ['name' => $user->name, 'email' => $user->email, 'verified' => $user->hasVerifiedEmail()];
            $changes = [];

            foreach ($after as $field => $value) {
                if ($value !== $before[$field]) {
                    $changes[$field] = ['old' => $before[$field], 'new' => $value];
                }
            }

            if ($changes !== []) {
                Audit::record(UserAdminEvent::UserUpdated, $user, context: ['changes' => $changes]);
            }
        });
    }
}
