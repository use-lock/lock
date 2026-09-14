---
paths:
    - "app/**"
---

# Architecture

## Organize by domain module, not technical layer

Every concern (models, enums, UI, HTTP, policies, actions) lives under `app/{Domain}/`, subdivided by technical role
only within that module — never a shared top-level technical folder (`app/Models`, `app/Http`, …) for domain code.
The existing modules are `Admin`, `Audit`, `Auth`, `Clients`, `Realms`, `Resources`, `Roles`, and `Shared`.

## Keep implementation with its owner and expose only real boundaries

A domain injects its own concrete actions directly. Do not add an interface or container binding for an action
that only its own controllers, forms or other actions call. Payloads, validation helpers and enums live in the
owning domain. `Shared` holds cross-cutting infrastructure and genuine contracts used between domains:
`DeletesUser`, `CreatesRealmUser`, `ProvidesRealmResources`, `ProvisionsConsoleClient`, and the audit seam.

The management API is owned by `Admin`: `ManagementApi`, `ManagementRoles`, `Enums\ManagementScope` and
`Enums\BootstrapOutcome` form its public application vocabulary. Domain audit enums are public vocabulary too;
the Audit module may consume them without importing another domain's actions or UI.

`User`, `Realm`, `Role`, `Resource` and `ResourceScope` remain concrete public model types for relations and route
bindings. Being public allows other domains to name a model; it does not exempt that model's own dependencies
from architectural checks. `architecturePublicTypes()` lists the public targets, and the architecture tests
check every source against the other domains' private classes without `ignoring()` exemptions.

## Multi-step writes always run inside a transaction

`DB::transaction(fn () => ...)` is the default; the manual `beginTransaction()`/`commit()`/`rollBack()` form is
acceptable when the closure doesn't fit, but a multi-step write must always be wrapped in a transaction of some form.

## Dates are immutable globally

`Date::use(CarbonImmutable::class)` is registered in `App\Shared\AppServiceProvider` — `now()` and model
date casts resolve to `CarbonImmutable`. Never use mutable `Carbon::` static calls.
