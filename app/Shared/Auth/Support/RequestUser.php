<?php
declare(strict_types=1);

namespace App\Shared\Auth\Support;

use App\Auth\Models\User;
use Illuminate\Http\Request;

/**
 * A `client_credentials` token resolves to a ClientPrincipal with no realm, no
 * roles and no `can()`, so calling a gate on it is a fatal error rather than a
 * denial.
 */
final class RequestUser
{
    public static function of(Request $request, ?string $guard = null): ?User
    {
        $user = $request->user($guard);

        return $user instanceof User ? $user : null;
    }

    public static function current(?string $guard = null): ?User
    {
        $user = auth()->guard($guard)->user();

        return $user instanceof User ? $user : null;
    }
}
