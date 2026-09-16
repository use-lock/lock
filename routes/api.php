<?php
declare(strict_types=1);

use App\Admin\Enums\ApiResource;
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
    ->middleware('auth:oidc')
    ->group(function (): void {
        Route::middleware(EnsureManagementApiAudience::class.':'.ApiResource::Admin->value)->group(function (): void {
            Route::middleware(EnsureScopes::using(ManagementScope::RealmsRead->value))->group(function (): void {
                Route::get('realms', [RealmController::class, 'index'])->name('realms.index');
                Route::get('realms/{realm}', [RealmController::class, 'show'])->name('realms.show');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::RealmsWrite->value))->group(function (): void {
                Route::post('realms', [RealmController::class, 'store'])->name('realms.store');
                Route::patch('realms/{realm}', [RealmController::class, 'update'])->name('realms.update');
                Route::delete('realms/{realm}', [RealmController::class, 'destroy'])->name('realms.destroy');
            });
        });

        Route::middleware(EnsureManagementApiAudience::class)->group(function (): void {
            Route::middleware(EnsureScopes::using(ManagementScope::SocialProvidersRead->value))->group(function (): void {
                Route::get('realms/{realm}/social-providers', [SocialProviderController::class, 'index'])->name('realms.social-providers.index');
                Route::get('realms/{realm}/social-providers/{social_provider}', [SocialProviderController::class, 'show'])->name('realms.social-providers.show');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::SocialProvidersWrite->value))->group(function (): void {
                Route::post('realms/{realm}/social-providers', [SocialProviderController::class, 'store'])->name('realms.social-providers.store');
                Route::patch('realms/{realm}/social-providers/{social_provider}', [SocialProviderController::class, 'update'])->name('realms.social-providers.update');
                Route::delete('realms/{realm}/social-providers/{social_provider}', [SocialProviderController::class, 'destroy'])->name('realms.social-providers.destroy');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::ClientsRead->value))->group(function (): void {
                Route::get('realms/{realm}/clients', [ClientController::class, 'index'])->name('realms.clients.index');
                Route::get('realms/{realm}/clients/{client}', [ClientController::class, 'show'])->name('realms.clients.show');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::ClientsWrite->value))->group(function (): void {
                Route::post('realms/{realm}/clients', [ClientController::class, 'store'])->name('realms.clients.store');
                Route::patch('realms/{realm}/clients/{client}', [ClientController::class, 'update'])->name('realms.clients.update');
                Route::delete('realms/{realm}/clients/{client}', [ClientController::class, 'destroy'])->name('realms.clients.destroy');
                Route::post('realms/{realm}/clients/{client}/secret', [ClientController::class, 'revealSecret'])->name('realms.clients.secret');
                Route::post('realms/{realm}/clients/{client}/rotate-secret', [ClientController::class, 'rotateSecret'])->name('realms.clients.rotate-secret');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::ResourcesRead->value))->group(function (): void {
                Route::get('realms/{realm}/resources', [ResourceController::class, 'index'])->name('realms.resources.index');
                Route::get('realms/{realm}/resources/{resource}', [ResourceController::class, 'show'])->name('realms.resources.show');
            });

            Route::middleware(EnsureScopes::using(ManagementScope::ResourcesWrite->value))->group(function (): void {
                Route::post('realms/{realm}/resources', [ResourceController::class, 'store'])->name('realms.resources.store');
                Route::patch('realms/{realm}/resources/{resource}', [ResourceController::class, 'update'])->name('realms.resources.update');
                Route::delete('realms/{realm}/resources/{resource}', [ResourceController::class, 'destroy'])->name('realms.resources.destroy');
            });
        });
    });
