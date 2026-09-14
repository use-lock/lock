<?php
declare(strict_types=1);

namespace App\Admin\Http\Api\OpenApi;

use App\Admin\Http\Middleware\EnsureScopes;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Two things no controller body reveals. The scopes a route requires live in
 * its `EnsureScopes` middleware — without them every operation would inherit the
 * document's bare requirement, and neither a reader nor the reference's
 * playground, which mints a token per operation scope set, could tell what a
 * call needs. And the guard rejects with the RFC 6750 error body rather than
 * Laravel's `AuthenticationException`, which is what Scramble infers from the
 * `auth` middleware alone.
 */
final class DocumentsTokenAuthorization extends OperationExtension
{
    public const string SCHEME = 'oauth2';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $scopes = $this->scopesOf($routeInfo);

        if ($scopes === []) {
            return;
        }

        $operation->security = [new SecurityRequirement([self::SCHEME => $scopes])];

        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            fn (Response|Reference $response): bool => $this->codeOf($response) !== 401,
        ));

        $operation
            ->addResponse($this->error(401, 'invalid_token', 'The access token is missing, expired, or not addressed to this API.'))
            ->addResponse($this->error(403, 'insufficient_scope', 'The access token does not grant '.implode(' and ', $scopes).'.'));
    }

    private function error(int $code, string $error, string $description): Response
    {
        return Response::make($code)
            ->description($description)
            ->setContent('application/json', (new ObjectType)
                ->addProperty('error', (new StringType)->enum([$error])->setDescription('The RFC 6749 error code.'))
                ->addProperty('error_description', (new StringType)->setDescription('A human readable explanation.'))
                ->setRequired(['error', 'error_description']));
    }

    private function codeOf(Response|Reference $response): ?int
    {
        $code = $response instanceof Response ? $response->code : $response->resolve()->code;

        return $code === null ? null : (int) $code;
    }

    /** @return list<string> */
    private function scopesOf(RouteInfo $routeInfo): array
    {
        $prefix = EnsureScopes::class.':';
        $scopes = [];

        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! Str::startsWith($middleware, $prefix)) {
                continue;
            }

            $scopes = [...$scopes, ...explode(',', Str::after($middleware, $prefix))];
        }

        return array_values(array_unique(array_filter($scopes)));
    }
}
