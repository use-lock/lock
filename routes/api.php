<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use App\Admin\Http\Middleware\EnsureManagementApiAudience;
use App\Admin\Http\Middleware\EnsureScopes;
use App\Clients\Http\Api\V1\Controllers\ClientController;
use App\Realms\Http\Api\V1\Controllers\RealmController;
use App\Realms\Http\Api\V1\Controllers\SocialProviderController;
use App\Resources\Http\Api\V1\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

/*
 * The management API is the master realm's protected resource, so it only
 * answers on the application host — RealmHostGate turns every other host away.
 * Authorization is scope only: a `client_credentials` token has no user behind
 * it, and nothing here reads one. When a person is behind the token,
 * EnsureScopes also intersects the required scopes with the ones their roles
 * still grant.
 */
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:oidc', EnsureManagementApiAudience::class])
    ->group(function (): void {
        Route::apiResource('realms', RealmController::class)
            ->middlewareFor(['index', 'show'], EnsureScopes::using(ManagementScope::RealmsRead->value))
            ->middlewareFor(['store', 'update', 'destroy'], EnsureScopes::using(ManagementScope::RealmsWrite->value));

        Route::apiResource('realms.social-providers', SocialProviderController::class)
            ->middlewareFor(['index', 'show'], EnsureScopes::using(ManagementScope::RealmsRead->value))
            ->middlewareFor(['store', 'update', 'destroy'], EnsureScopes::using(ManagementScope::RealmsWrite->value));

        Route::apiResource('realms.clients', ClientController::class)
            ->middlewareFor(['index', 'show'], EnsureScopes::using(ManagementScope::ClientsRead->value))
            ->middlewareFor(['store', 'update', 'destroy'], EnsureScopes::using(ManagementScope::ClientsWrite->value));

        Route::post('realms/{realm}/clients/{client}/secret', [ClientController::class, 'revealSecret'])
            ->name('realms.clients.secret')
            ->middleware(EnsureScopes::using(ManagementScope::ClientsWrite->value));
        Route::post('realms/{realm}/clients/{client}/rotate-secret', [ClientController::class, 'rotateSecret'])
            ->name('realms.clients.rotate-secret')
            ->middleware(EnsureScopes::using(ManagementScope::ClientsWrite->value));

        Route::apiResource('realms.resources', ResourceController::class)
            ->middlewareFor(['index', 'show'], EnsureScopes::using(ManagementScope::ResourcesRead->value))
            ->middlewareFor(['store', 'update', 'destroy'], EnsureScopes::using(ManagementScope::ResourcesWrite->value));
    });
