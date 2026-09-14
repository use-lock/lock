---
paths:
    - "app/*/Services/**"
    - "app/*/Data/**"
---

# Services and Data

## A class that carries domain logic behind an injectable is a service

It belongs in `app/{Domain}/Services/`, resolved from the container by type hint. No `Service` suffix is required —
name it for what it does.

`Support/` stays for the smaller helpers that are not services and not data: pure functions over their arguments,
static registries, string manglers, and thin adapters a config entry names by class-string rather than the container
injecting. `App\Realms\Support\RealmConfiguration` and `App\Resources\Support\ResourceScopeCatalog`
(wired through `config('oidc.scopes')`) are the shape that belongs there.

## Value objects that carry data between layers live in Data/

`app/{Domain}/Data/` holds the immutable value objects a service returns and the UI consumes — `readonly` classes
with typed, promoted properties, no Eloquent, no container.

## A write's payload is a laravel-data object, and it is what the action takes

An incoming payload is a `spatie/laravel-data` class, never a Form Request: the API resolves it from the request and
the console's Lattice form builds the same object, so both reach the domain action through one signature. It lives in
`app/{Domain}/Data/` beside the other value objects. Keep the payload with the action that consumes it.

Extend `App\Shared\Data\ApiData` for anything built from a request: its pipeline rejects payload keys that map to
no property, so a typo fails with a 422 instead of being silently dropped. Describe every property with Spectacular's
`#[SpecProperty]`, which is what the generated document and the API reference read. Declare a property as
`Optional|T` when leaving it out means "keep what is there", and no `rules()` method — that hands the whole class
back to Scramble and drops the descriptions.

Transport constraints (a length, a pattern, a format) belong on the property as validation attributes. An invariant
of the record — a uniqueness that spans rows, a rule comparing two fields against stored values — stays in the
action, so every caller is held to it and the payload class stays a description of the wire.
