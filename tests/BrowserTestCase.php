<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Http\Request;
use Lattice\Support\Testing\KeepsBrowserConnectionsAlive;
use Pest\Browser\Playwright\Playwright;
use Pest\Browser\ServerManager;

abstract class BrowserTestCase extends TestCase
{
    use KeepsBrowserConnectionsAlive;

    protected bool $stubsVite = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serveTheMasterRealmFromTheBrowserServer();
    }

    protected function tearDown(): void
    {
        Playwright::setHost(null);

        parent::tearDown();
    }

    /**
     * The plugin points `app.url` at its own server, which makes that server's
     * host the master realm's; what a test runs in-process has to resolve the
     * realm from the same host.
     */
    private function serveTheMasterRealmFromTheBrowserServer(): void
    {
        ServerManager::instance()->http()->bootstrap();

        app()->instance('request', Request::create((string) config('app.url')));
    }
}
