<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use App\Shared\Auth\Contracts\CreatesRealmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lock\Server\Authentication\Actions\SendPasswordResetLink;
use SensitiveParameter;

final readonly class CreateRealmUser implements CreatesRealmUser
{
    public function __construct(private SendPasswordResetLink $sendResetLink) {}

    /**
     * Without a password the account starts with one nobody knows, and the
     * invitation is the reset link that lets the user choose their own.
     */
    public function handle(
        Realm $realm,
        string $name,
        string $email,
        #[SensitiveParameter] ?string $password,
        bool $verified,
    ): User {
        $invited = $password === null;

        $user = DB::transaction(function () use ($realm, $name, $email, $password, $verified, $invited): User {
            $user = User::create([
                'realm_id' => $realm->id,
                'name' => $name,
                'email' => $email,
                'password' => $password ?? Str::password(32),
            ]);

            if ($verified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            Audit::record(UserAdminEvent::UserCreated, $user, context: [
                'email' => $user->email,
                'realm' => $realm->slug,
                'verified' => $verified,
                'invited' => $invited,
            ]);

            return $user;
        });

        if ($invited) {
            DB::afterCommit(fn () => $realm->runAsCurrent(fn (): string => ($this->sendResetLink)($user->email)));
        }

        return $user;
    }
}
