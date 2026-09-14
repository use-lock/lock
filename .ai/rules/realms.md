---
paths:
    - "app/Auth/**"
    - "app/Admin/**"
    - "app/Realms/**"
    - "config/auth.php"
---

# Realm-local identity

## Every user lookup carries a realm constraint

`users` is unique on `(realm_id, email)`, so one address can be two unrelated people. `config/auth.php` therefore
runs the `realm-eloquent` driver, not `eloquent`: `App\Auth\Support\RealmUserProvider` overrides `newModelQuery()` —
the one seam `retrieveById`, `retrieveByToken` and `retrieveByCredentials` all build on — and scopes it to
`Realm::current()`.

There is always a current realm, so the constraint protects every login, session and token lookup. Do not
"simplify" the driver back to `eloquent`: without it a password valid in one realm authenticates in all of them, and
a session cookie minted in one resolves a user in another. `tests/Feature/Auth/RealmIsolationTest.php` fails on
every one of those.

## Acting on another realm's user needs runAsCurrent

Admin spans realms; credential lookup and links do not. Anything that resolves a user by address outside the actor's
own realm, or mails them a link — `SendUserPasswordReset`, `ResendUserVerification` and the invitation in
`CreateRealmUser` — must run inside `$user->realm->runAsCurrent(…)`. Otherwise the user provider does not find them, use-lock/server's
reset link (`SendPasswordResetLink`, whose token belongs to the realm that sent it) is minted for the wrong realm, and
the link lands on the console's host: use-lock/server points every absolute URL to a route behind `ResolveRealm` at the
current realm's host.

## The realm comes from the request host

`config('oidc.routes.realms')` is `domain`: every realm is served from a host of its own, the master realm from the
host in `APP_URL` (never stored, `Realm::masterHost()`), every other realm from `realms.domain`. The browser isolates
the realms by origin, so there is no per-realm session cookie any more; `SESSION_DOMAIN` has to stay empty, or realms
on subdomains of one parent would share a cookie. Do not bring back a `/realms/{slug}` path or a cookie-name setting.

The current realm is use-lock/server's: its `DomainRealmResolver` reads it from the request host on every request, and
`Realm::current()` hands the app the `realms` row. `$realm->runAsCurrent()` (the package's `CurrentRealm::runAs()`)
overrides it for a callback, and the jobs dispatched inside carry it to the worker; a console command that names no
realm runs in the master realm (`oidc.realm`). Do not bring back a realm of the app's own in `Context` or a middleware
that sets one from the signed-in user: two answers to "which realm" is how a lookup ends up in the wrong one. An admin
page's `{realm}` parameter is data, not the acting realm.

`App\Realms\Support\RealmHostGate` runs on `RouteMatched`: a host no realm is served from gets a 404 on everything
but `/up` and the domain check (which keeps a forged Host header out of every URL the app would mint from it), the
master realm's host serves everything, and any other realm's host serves only the routes behind `ResolveRealm` plus
the translations — its root redirects to the account page. The gate also points the WebAuthn relying party at the host,
because a passkey is bound to the host it was created on.

## A realm user's pages live on the realm host, and so do their Lattice endpoints

`App\Auth\Ui\Pages\AccountPage` is registered at `/account` with the package's middleware order — `ResolveRealm`,
then `web`, then `AuthenticateIdentity:identity` — and `bootstrap/app.php` pins `ResolveRealm` before `StartSession`
in the middleware priority so a page may list them in any order. The root Lattice endpoints (`/lattice/forms/…`)
authenticate with the console's `web` guard, which on the master realm's host shares the session with the identity
guard, so `AuthServiceProvider` registers Lattice's `account` endpoint area (`/account/lattice/…`, behind the same
middleware as the page) and the account page names it (`#[AsPage(endpoints: 'account')]`). use-lock/server renders its
two-factor setup page from its own controller, so Lock binds `App\Auth\Ui\Pages\SetupTwoFactorPage`, a subclass that
names the area, as the package's `FactorSetupView`. `Realm::login()` sends a realm login home to that account page and a realm logout back
to the realm's own sign-in — never to `/` on the master host, which is its relying-party entry.

## A realm's domain is checked, never trusted

`App\Realms\Actions\CheckRealmDomain` asks `https://{domain}/.well-known/lock/domain-check` for an HMAC of the host
under `APP_KEY` (`App\Realms\Support\DomainCheckToken`) and records `RealmDomainStatus` on the realm: DNS, the
platform proxy and TLS all have to lead to this instance, redirects are not followed. The console runs it after
creating a realm, after changing its domain and on request; an unverified realm is still created and served. The
check is an outbound request to an administrator-typed host, so `App\Realms\Support\RealmDomain` only admits
lowercase host names — no IP literals, ports or paths.

## use-lock/server reads realms from the database

`App\Realms\Support\DatabaseRealmRepository` (bound in `AuthServiceProvider::register()`) hands the package's resolver
the `realms` row for the request host (`findByDomain()`), and `App\Realms\Models\Realm` implements
the package's `Realm` contract by reading its settings (`realm.*` keys, seeded from
`App\Realms\Support\RealmConfiguration::defaults()` when the realm is created). Package rows (clients, tokens,
sessions, consents) carry the realm slug in `realm_id`, so a client only exists inside the realm it was created in:
provision clients — including the first-party client — with that realm current
(`Realm::master()->runAsCurrent(...)`, as `DatabaseSeeder` does), or the package will not find them at login.

The package names a realm's host through `Realm::host()` and builds the issuer from it with the scheme and port of
`oidc.issuer`. Lock's own settings live in `config/lock.php`; `AppServiceProvider::configureOidc()` derives the
package values that follow from them — `oidc.realm` from `lock.master_realm` (`admin` by default, `LOCK_MASTER_REALM`),
and the relying party's client from `lock.console_client` — so the app authenticates against `APP_URL` itself.

## Global roles only count inside the master realm

`User::hasGlobalPermission()` returns false for a user outside the master realm whatever roles they hold, and
a role outside it holds no management scopes whatever its rows read. Instance administrators live in the master
realm; every other realm is data to them, reachable through `User::canAccessRealm()` and the `realms:write`
permission rather than membership.
