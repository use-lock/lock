---
paths:
    - "database/migrations/**"
---

# Migrations

## UUID primary keys everywhere

Every domain table uses `$table->uuid('id')->primary()`, never `$table->id()`. Foreign keys are declared with
`foreignUuid()->constrained()` to match. Framework-owned tables (cache, jobs, sessions) keep their shipped shape.

## Enum-typed columns are string(), never the DB enum() type

Declare enum-backed columns as `string()` with an explicit length and cast to a PHP backed enum on the model —
a DB-level `enum()` needs a migration to add a case.

## Migrations contain schema changes only

Migrations only alter schema. Seeding and data backfills belong in `database/data-migrations/` (see
`.ai/rules/data-migrations.md`), never in `database/migrations/`.
