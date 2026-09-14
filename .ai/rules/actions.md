---
paths:
    - "app/*/Actions/**"
---

# Actions

## Actions are plain classes with a free-form entry method

Action classes have no required base class or interface and no fixed method name — `handle`, `execute`, or a domain
verb are all fine. Wrap multi-step writes in `DB::transaction()` (see the architecture rule on transactions).

## Domain writes belong in an action, not in a Lattice form

A Lattice form or action owns authorization, transport validation, and the response effects. The mutation itself,
its invariants, and its notifications belong in a plain class under `app/{Domain}/Actions/` so a controller, a
console command, or a job can reach the same behavior. Do not add base actions, repositories, or CRUD services.
