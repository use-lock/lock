---
paths:
    - "database/data-migrations/**"
---

# Data Migrations

## Data migrations are separate from schema migrations

`database/data-migrations/` is the home for data changes and backfills — default reference data, one-off corrections.
`database/migrations/` stays schema-only. Never put a data change in `database/migrations/`.

## They run through the same migrate command, on a separate path

Trigger them with `php artisan migrate --path=database/data-migrations` — the standard Laravel Migrator, not a custom
command. Files follow the same anonymous-class-extends-`Migration` shape as `database/migrations/`.

Like schema migrations they are one-way (no `down()`) and tracked in the `migrations` table, so a file runs exactly once
per environment, ever. Write `up()` as a plain, direct `create()`/`insert()` — no `updateOrCreate` or other idempotency
handling, that is the Migrator's job, not the migration's.

`app:deploy` runs the folder after schema migrations. Local setup, `composer fresh` and worktree creation call
that same workflow. `Tests\TestCase` runs it before transactions; `DatabaseSeeder` adds demo data only.

## Repeatedly-applied defaults stay seeders

Reference data that a deploy has to re-apply — the protected global `Super Admin`/`Support` roles from
`App\Admin\Database\Seeders\GlobalRoleSeeder` — belongs in an idempotent seeder, not here. A data migration is for data
that is written once and then owned by the environment.

## Keep them narrow, one concern per file

Split by who needs the data, not by what was historically seeded together. Data that only a few tests care about should
stay opt-in — those tests build their own fixture row with a factory instead of leaning on a migration.

## No dedicated tests for a data migration's contents

The tests that exercise the data are the real coverage. Don't add a test whose only job is asserting what an `up()`
inserted. The wiring needs no test of its own either: every test that reaches the master realm fails without it.
