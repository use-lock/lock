<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lock\Server\Shared\Brokering\CreateUserFromSocialAccount;
use Lock\Server\Shared\Brokering\SocialAuthenticationException;
use Lock\Server\Shared\Brokering\SocialUser;

final class CreateUserFromSocialLogin implements CreateUserFromSocialAccount
{
    public function __invoke(SocialUser $socialUser, string $provider): User
    {
        $realm = Realm::current();
        $email = $socialUser->email;

        if ($email === null) {
            throw new SocialAuthenticationException("The [{$provider}] identity carries no email address.");
        }

        /*
         * An unverified upstream address would let anyone who can name it
         * claim a realm identity, and the address is the realm's unique key.
         */
        if (! $socialUser->emailVerified) {
            throw new SocialAuthenticationException("The [{$provider}] identity's email address is not verified.");
        }

        return DB::transaction(function () use ($realm, $socialUser, $email): User {
            $user = $realm->users()->firstOrCreate(['email' => $email], [
                'name' => $socialUser->name ?? $socialUser->nickname ?? Str::before($email, '@'),
                'password' => Str::password(32),
            ]);

            if (! $user->wasRecentlyCreated) {
                throw new SocialAuthenticationException('A local account already uses this email address.');
            }

            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });
    }
}
