<?php
declare(strict_types=1);

namespace App\Admin\Http\Middleware;

use App\Admin\Enums\ApiResource;
use App\Admin\ManagementApi;
use Closure;
use Illuminate\Http\Request;
use Lock\Server\Tokens\Http\Middleware\CheckAudience;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guard only checks that a token is addressed to one of the realm's
 * audiences; RFC 9068 §4 wants this resource to verify it is addressed to
 * itself. The identifier follows the issuer, so it can only be resolved once
 * the realm is current — which rules out naming it in the route definition.
 */
final readonly class EnsureManagementApiAudience
{
    public function __construct(
        private ManagementApi $api,
        private CheckAudience $audience,
    ) {}

    public function handle(Request $request, Closure $next, string $resource = ApiResource::Management->value): Response
    {
        return $this->audience->handle($request, $next, $this->api->audience(ApiResource::from($resource)));
    }
}
