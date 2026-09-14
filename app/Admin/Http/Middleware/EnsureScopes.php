<?php
declare(strict_types=1);

namespace App\Admin\Http\Middleware;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Shared\Auth\Support\RequestUser;
use Closure;
use Illuminate\Http\Request;
use Lock\Server\Shared\Protocol\OAuthServerException;
use Lock\Server\Tokens\Http\Middleware\CheckScopes;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token's scopes are only half the answer when a person is behind it. A
 * token outlives the role that justified it, so the person's own scopes are
 * intersected on every request and a revoked role takes effect at once. A
 * `client_credentials` token has no person and is judged on its scopes alone,
 * which is what the package's middleware already does.
 */
final class EnsureScopes extends CheckScopes
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = RequestUser::of($request);

        if ($user instanceof User) {
            foreach ($scopes as $scope) {
                $case = ManagementScope::tryFrom($scope);

                if (! $case instanceof ManagementScope || ! $user->hasManagementScope($case)) {
                    throw OAuthServerException::insufficientScope();
                }
            }
        }

        return parent::handle($request, $next, ...$scopes);
    }
}
