<?php
declare(strict_types=1);

use App\Audit\Support\DatabaseAuditSink;
use App\Resources\Support\ResourceScopeCatalog;

return [
    // Null, so the origin comes from `app.url`: every realm host shares its
    // scheme and port.
    'issuer' => null,

    'routes' => [
        'realms' => 'domain',
    ],

    // mergeConfigFrom only merges the top level, so naming a sink here drops
    // the package's other audit keys unless they are repeated.
    'audit' => [
        'enabled' => env('OIDC_AUDIT_ENABLED', true),
        'sink' => DatabaseAuditSink::class,
        'log_channel' => env('OIDC_AUDIT_LOG_CHANNEL'),
    ],

    // A scope belongs to exactly one resource, so the catalog is read per
    // requested RFC 8707 `resource`.
    'scopes' => ResourceScopeCatalog::class,

    // Lock owns both tables the package points at, so both keys are set: the
    // realm is keyed by `slug` rather than by `id`, because the slug is what
    // every `realm_id` column holds.
    'migrations' => [
        'users' => ['table' => 'users', 'column' => 'id'],
        'realms' => ['table' => 'realms', 'column' => 'slug'],
    ],
];
