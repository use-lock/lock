<?php
declare(strict_types=1);

use App\Admin\Console\Commands\BootstrapCommand;
use App\Admin\Console\Commands\GrantAdminCommand;
use App\Shared\Auth\Support\RequestUser;
use App\Shared\Console\Commands\DeployCommand;
use App\Shared\Console\Commands\SetupCommand;
use App\Shared\Http\Middleware\HandleAppearance;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\FrameGuard;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Inertia\Middleware as InertiaMiddleware;
use Lock\Server\Realms\Http\Middleware\ResolveRealm;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS-terminating reverse proxy, generated asset and route
        // URLs are http:// without this and the browser blocks them as mixed
        // content. Only safe while the app is exclusively reachable through
        // the proxy — narrow it to the proxy's address otherwise.
        $middleware->trustProxies(at: '*');

        // Octane's Caddyfile sets no security headers, and a login page another
        // origin can frame is open to clickjacking — global, so the OIDC
        // package's own routes are covered as well.
        $middleware->append(FrameGuard::class);

        $middleware->encryptCookies(except: ['appearance']);

        // A signed-in identity goes straight to its realm's account page instead
        // of through the console's relying-party login behind the home route.
        $middleware->redirectUsersTo(function (Request $request): string {
            $identity = RequestUser::of($request, 'identity');

            return $identity === null ? '/' : route('account');
        });

        $middleware->web(append: [
            HandleAppearance::class,
            InertiaMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // A realm's page may list the web group before the realm middleware;
        // the realm still has to be resolved before the session starts, so
        // priority pins it there whatever the order.
        $middleware->prependToPriorityList(before: StartSession::class, prepend: ResolveRealm::class);

        $middleware->alias([
            // The framework default redirects to verification.notice, which no
            // route registers — the OIDC package names its verification prompt
            // identity.verification.notice.
            'verified' => EnsureEmailIsVerified::redirectTo('identity.verification.notice'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withCommands([
        BootstrapCommand::class,
        DeployCommand::class,
        GrantAdminCommand::class,
        SetupCommand::class,
    ])->create();
