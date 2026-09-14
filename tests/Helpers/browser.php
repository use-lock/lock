<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Auth\Models\User;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;

use function visit;

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
