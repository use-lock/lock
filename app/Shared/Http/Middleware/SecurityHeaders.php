<?php
declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Octane's Caddyfile sets no security headers, so the app sends them itself.
 * A header a response already carries wins, so a route can still loosen one.
 */
final class SecurityHeaders
{
    private const array HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // No script-src yet: Inertia and Vite need nonces first. form-action is
        // left out because consent redirects to arbitrary client callbacks.
        'Content-Security-Policy' => "frame-ancestors 'self'; object-src 'none'; base-uri 'self'",
    ];

    // Without includeSubDomains: other hosts under a realm's domain belong to
    // whoever runs that domain, not to Lock.
    private const string STRICT_TRANSPORT_SECURITY = 'max-age=31536000';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT_SECURITY);
        }

        return $response;
    }
}
