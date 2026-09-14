<?php
declare(strict_types=1);

return [

    // mergeConfigFrom only merges the top level, so the package's `tracing` and
    // `breadcrumbs` keys stay where they are and are tuned through their own
    // environment variables.
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

];
