<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Auth\Models\User;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Event;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;

use function visit;

/**
 * The browser server points `app.url` at whichever host a request came in on,
 * which would make every host the master realm's. Putting it back before the
 * route is matched lets a visit reach another realm the way production does.
 */
function keepTheMasterRealmOnItsOwnHost(): void
{
    $masterUrl = (string) config('app.url');

    Event::listen(Routing::class, fn () => config(['app.url' => $masterUrl]));
}

/**
 * The browser server rewrites every visit to its own address, so the realm host
 * travels as the Host header; BrowserTestCase resets it after the test.
 */
function visitAccountAs(User $user): PendingAwaitablePage
{
    runningTestCase()->actingAs($user, 'identity');

    if (! $user->realm->isMaster()) {
        Playwright::setHost($user->realm->host());
    }

    return visit('/account');
}
