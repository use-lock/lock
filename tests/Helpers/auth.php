<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Auth\Models\User;
use Illuminate\Auth\SessionGuard;
use Laravel\Passkeys\Passkey;

function createPasskey(User $user, string $name = 'My passkey'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => 'cred-'.fake()->unique()->uuid(),
        'credential' => ['type' => 'public-key'],
    ]);
}

/**
 * @return array<string, string>
 */
function identitySessionFor(User $user): array
{
    return ['login_identity_'.sha1(SessionGuard::class) => $user->getKey()];
}

/**
 * @return array<string, string>
 */
function consoleSessionFor(User $user): array
{
    return ['login_web_'.sha1(SessionGuard::class) => $user->getKey()];
}
