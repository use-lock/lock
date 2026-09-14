<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Models\Realm;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Lock\Server\Realms\Http\Middleware\ResolveRealm;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A host no realm is served from gets nothing, which also keeps a forged Host
 * header out of every URL the app mints from it.
 */
final class RealmHostGate
{
    public function guard(Request $request, Route $route): void
    {
        // The health check comes in on whatever host the platform probes, the
        // domain check on a host before any realm is served from it.
        if ($route->uri() === 'up' || $route->named('realm.domain-check')) {
            return;
        }

        $realm = Realm::findByHost($request->getHost()) ?? throw new NotFoundHttpException;

        // WebAuthn binds a passkey to the host it was created on.
        config([
            'passkeys.relying_party_id' => $realm->host(),
            'passkeys.allowed_origins' => [$realm->origin()],
        ]);

        // A realm's sign-in pages load their translations from its own host.
        if ($realm->isMaster() || ResolveRealm::appliesTo($route) || $route->named('i18next.*')) {
            return;
        }

        if ($route->named('home')) {
            throw new HttpResponseException(to_route('account'));
        }

        throw new NotFoundHttpException;
    }
}
