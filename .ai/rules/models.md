---
paths:
    - "app/*/Models/**"
---

# Models

## UUID primary keys on every domain model

Every domain model uses `HasUuids`; never rely on auto-increment integer ids for domain tables.
`tests/Feature/ArchitectureTest.php` discovers domain models and asserts this for every application-owned model.

## Mass assignment via the #[Fillable] attribute

Declare mass-assignable attributes with the `#[Fillable([...])]` class attribute, never the legacy `$fillable` /
`$guarded` properties.

## Models extending a vendor model keep the vendor's key strategy

Read package-owned clients and tokens through the package model classes. use-lock/server writes and hydrates its
own models; an application subclass does not replace those call sites.

## Realm-owned models are reached through their realm

The console reads a realm's rows while the administrator's own realm is current, so realm-owned models carry no
global scope. `App\Roles\Models\Role`, `App\Resources\Models\Resource` and its `ResourceScope` are plain models reached through
`$realm->roles()` and `$realm->realmResources()`, never a hand-written `realm_id` constraint. `Resource` and
`ResourceScope` are listed in `architecturePublicTypes()` because the admin console binds a `Resource` by route and
names it in contracts. `Realm::resources()` is the package's settings
contract, not the relation; the relation has to be called something else.

`resource` is a soft-reserved PHP type keyword, so Pint's `phpdoc_types` fixer lowercases a bare `Resource` in any
docblock and PHPStan then rejects the case. Write the class fully qualified (`\App\Resources\Models\Resource`) in
generics, `@property` tags and `@extends` clauses.

`Realm` hard-deletes — a deleted realm frees its slug — and `App\Realms\Actions\DeleteRealm` is the only place that removes one, because the package rows keyed by the realm slug have no foreign key to cascade from. User and realm deletion raise the app’s `UserDeleting` and `RealmDeleting` events; adapters forward them to the OIDC server’s deletion events. Client deletion goes through its repository. The package owns cleanup of its tables; never list those tables in the app.

## Lifecycle hooks live on the model, in a #[Boot] method

A model's `creating`/`updating`/`deleting`/… hooks are registered in a static method the model carries, tagged
`#[Boot]` — not in an observer class, and not in a `boot()` override. The invariants a model refuses to break read
next to the attributes they guard, which is why `App\Realms\Models\Realm` pins its immutable slug and the
undeletable master realm there. There is no `app/{Domain}/Observers/` folder.

## Persist stable audit subject aliases

Each owning service provider merges its model aliases into `Relation::morphMap()`. Use stable kebab-case aliases
such as `resource-scope` and `social-provider`; renaming a PHP namespace must not change persisted audit subjects.
Audit records and resolves subjects through Eloquent and does not maintain a registry of domain model classes.
