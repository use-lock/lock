<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules every maintenance command the OIDC server leaves to the application', function () {
    $scheduled = implode("\n", array_map(fn (Event $event): string => (string) $event->command, app(Schedule::class)->events()));

    foreach (['oidc:prune', 'oidc:dispatch-expired-session-logouts'] as $command) {
        expect($scheduled)->toContain($command);
    }
});
