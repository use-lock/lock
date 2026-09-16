---
paths:
    - "app/*/Http/**"
---

# Http

## Split each domain's Http/ by purpose, and version the API

`Http/Controllers` for web controllers, `Http/Requests` for their Form Requests, `Http/Middleware` for middleware.
A token-authenticated JSON endpoint — one gated in `routes/api.php` by `EnsureScopes::using(ManagementScope::…)` — goes under
`Http/Api/V1/Controllers`, with the `spatie/laravel-data` class it answers with in `Http/Api/V1/Resources` — there is
no `JsonResource` here, and no Form Request either: what a write accepts is a laravel-data object too (see
`.ai/rules/services.md`). Never put an API controller or resource directly under `Http/Controllers` or
`Http/Resources`: the version segment is what makes a breaking API change a new folder instead of an edit to the one
every client already calls.

A domain never reaches into another domain's `Http/`.

## API endpoints authenticate with auth:oidc

`routes/api.php` runs on the `oidc` guard that `use-lock/server` registers itself; `config/auth.php` declares no `api`
guard at all. Gate a route with `App\Admin\Http\Middleware\EnsureScopes::using(ManagementScope::…->value)`, never the
package's `CheckScopes` directly: a token outlives the role that justified it, so when a person is behind the token
`EnsureScopes` also intersects the required scopes with the ones their roles still grant. A `client_credentials`
token has no person and is judged on its scopes alone (see `.ai/rules/enums.md`).

The API is the master realm's own protected resource. `App\Admin` owns it: `ManagementScope` is the scope catalog,
`ManagementApi` reconciles the resource row and its scopes (from `app:bootstrap`, so every deploy restores them) and
answers which rows the console must refuse to touch. Each route group narrows its tokens to one `ApiResource` with
the package's `CheckAudience::using(ApiResource::…)`, because the guard only checks a token is addressed to _one_ of
the realm's audiences; the relative identifier resolves under the issuer at request time. The scopes each
operation requires reach the OpenAPI document through `DocumentsTokenAuthorization`, which reads them off the route's
`EnsureScopes` middleware and replaces Scramble's inferred `AuthenticationException` with the RFC 6750 error body the
guard really returns. The reference's playground mints a token per operation scope set from exactly those scopes, so
an operation whose security requirement is missing cannot be executed there.

## Behind auth:oidc, the principal is not always a person

A `client_credentials` token passes the `auth:oidc` guard and resolves to a
`Lock\Server\Tokens\Guard\ClientPrincipal` — a client acting for itself, with no realm, no roles
and no `can()`. Reaching for any of those on it is a fatal error, not a denial, and larastan types
`$request->user()` as the app's `User`, so static analysis will not warn you.

`App\Shared\Auth\Support\RequestUser::of($request)` (and `::current()`) is the one seam that answers "is there a
person here": it returns the `User` or null. Anything that means a person — a gate check, a realm lookup, shared
Inertia props — goes through it rather than testing the principal's class at the call site.

## Middleware is attached at the route or group level

Assign middleware via `->middleware()` in `routes/*.php` or via `#[AsPage(middleware:)]`, never inside a controller.
