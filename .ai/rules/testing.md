---
paths:
    - "tests/**"
---

# Testing

## Pest, with RefreshDatabase applied globally

Tests are Pest. `Tests\TestCase` uses `RefreshDatabase` itself, and `tests/Pest.php` binds that base class to the
`Feature` and `Browser` suites — never re-add `uses(RefreshDatabase::class)` in a test file, and never move the trait
back onto the Pest binding: a trait on the per-file class Pest generates wins over the inherited method and would
shadow `TestCase::migrateDatabases()`.

## Data migrations and the protected roles are already there in every test

`Tests\TestCase` runs `database/data-migrations` and `GlobalRoleSeeder` once per process, before the per-test
transaction opens — the master realm and the `Super Admin`/`Support` roles are present in every `Feature` and
`Browser` test, and per-test mutations of them roll back. Never run them explicitly (no
`artisan migrate --path=database/data-migrations` or `GlobalRoleSeeder` in a test). Explicit `$this->seed()` is only for a domain seeder a
test actually exercises; never seed the full `DatabaseSeeder` except when the test is about the seeder itself.

## Prefer feature tests

Exercise the application through HTTP endpoints, Lattice forms/actions/tables, jobs, commands, events, policies, and
database effects rather than isolating internals by default. Use unit tests only for complex pure algorithms or
small deterministic value objects.

## Lattice UI is exercised via InteractsWithLatticeComponents

`Tests\TestCase` carries the trait: `submitForm(FormClass, $data, $context)`, `callAction(ActionClass, $data,
$context)`, `loadTable(TableClass, $query, $context)`, plus the `…Denied` variants (`submitDeniedForm`,
`callDeniedAction`, `loadDeniedTable`, `callDeniedBulkAction`) for asserting an unauthorized component is refused.
Chaining a `submitForm` then a `callAction` in one test works — Lattice refreshes the request identity between
seals. Never fake client behavior in a feature test: asserting fragments of the serialized payload as a proxy for
"the UI works" is not UI coverage. If the behavior only exists in the browser, write a browser test.

## Flashed effects are asserted with assertLatticeEffects

A page render drains the `latticeEffects` flash bag client-side, so a toast, callout, redirect or retraction a
middleware or listener flashed never appears in the rendered schema. Assert it with
`$this->assertLatticeEffects($response)->assertFlashed('callout', fn (array $props) => …)`,
`->assertNotFlashed()`, `->assertNothingFlashed()` or `->props($type)` — never by reading `flash.latticeEffects`
out of the Inertia page yourself.

## Personal access tokens need a personal-access client

`app(ClientRepository::class)->createPersonalAccessGrantClient($name, 'users')` — there is no console command for
it, and the provider argument is what binds the client to the guard's user provider.

## Authenticate API tests against the oidc guard

`Tests\TestCase` carries the package's `InteractsWithOidc` trait: `$this->withToken($this->issueTokenFor($user,
scopes: [...]))` mints and persists a real signed token, `$this->actingAsOidcUser($user, $scopes)` sets the guard's
user without a token row — code that reads the token row has to tolerate that. Users have to live in the master
realm (`User::factory()->for(Realm::master())`) for any flow that goes through the identity login or reads a token
on the application host: that host is the master realm, and the realm constraint on user lookup holds there. Between two differently-authenticated requests in one test, call
`auth()->forgetGuards()`: the oidc guard caches its resolved user and only gets a refreshed request, so the previous
bearer token would otherwise still authenticate.

## Every realm has a host, and a test addresses it

Realms are served from their own host (see `.ai/rules/realms.md`), so a request to a realm's pages goes to that host:
`realmUrl($realm, '/auth/login')` for a path, `realmRoute($realm, 'identity.login')` for a named route. A relative
path resolves against the host of the previous request, so a test that has talked to a realm addresses the
application host through `realmUrl(Realm::master(), …)` as well. A fixture realm lives on `{slug}.localhost`, which
browsers resolve to the loopback address; in a browser test the pest server rewrites every visit to its own address,
so `visitAccountAs()` sends the realm host as the Host header instead. A domain check never leaves the process:
`domainReachesThisInstance()` answers every one as this instance, `Http::fake()` anything else.

## What a test must earn

Every test asserts an observable behavior change caused by an interaction, input, or state transition. Delete on
sight:

- **Render-only tests** — a page loads and shows static text, or a class is merely present in the payload, with no
  interaction.
- **Styling pins** — assertions on Tailwind utility classes. Assert semantic state instead (`aria-*`, `data-*`,
  disabled, visibility driven by state).
- **Absence-only assertions** — `assertDontSee()` on initial render, unless the same test establishes the positive
  case too.
- **Tautologies** — asserting a factory or mock returns what it was configured with, or that a config value is its
  own default.
- **Obsolete regression pins** — tests named after a completed refactor or a finished file move.
- **Duplicated coverage** — every behavior has exactly one owning test. Don't re-assert a lower layer's contract
  (Lattice field validation, table pagination internals, framework behavior) in every consumer.

## Keep library behavior in its owning library

Before writing a test, identify which layer owns the behavior. Generic behavior from Lattice or another vendor
package belongs in that package's suite, even when the regression first showed up here. Test only this app's
configuration, composition, and observable integration behavior.

## tests/ is analysed at PHPStan level 8, with no baseline

Keep it at zero. When a nullable trips the analyser, narrow it at the source: don't re-fetch a model you already
hold, prefer `->refresh()` (returns `$this`) over `->fresh()` (returns `?static`), and reach for
`findOrFail()`/`firstOrFail()`/`sole()` where the row must exist. Only when none of those fit, call
`Tests\Helpers\assumeNotNull($value)` — it carries `@phpstan-assert !null` and throws if the assumption is ever
wrong. Never silence an error with `@phpstan-ignore`, a baseline entry, `assert()`, an inline `@var`, or a cast.
A `?: []` fallback on a failed `glob()`/`file_get_contents()` is worse than any of those: it makes the test pass
vacuously. Throw instead.

The `pest-plugin-phpstan` extension reports assertions that cannot fail (`toBeString()` on a typed `string`) as
`pest.expectation.redundant`. Those are the tautologies this rule already bars — delete them rather than typing
around them.

## Fixtures via Model::factory()

Build fixtures with `Model::factory()`, checking for a custom state first. Reach for a raw `DB::table()->insert()`
only for a pivot row that has no factory relationship of its own.

## Shared helpers live in tests/Helpers

Shared helper functions are namespaced `Tests\Helpers` in `tests/Helpers/*.php` (Pest auto-loads the directory) and
imported per file with `use function Tests\Helpers\globalAdmin;`. `tests/Pest.php` holds only the
`pest()->extend()` bindings. Never define a new global (unnamespaced) shared helper: every file-local test helper
shares one global function table across the suite, so a name reused in a second file is a fatal redeclare.
`globalAdmin()` (an administrator holding a protected role), `globalAdminWith(Scope ...)` (one holding
exactly those scopes — the actor for a "forbidden without X" test), `identitySessionFor()`/`consoleSessionFor()`
(a signed-in session for either guard) and `realmSetting()` (one realm setting as the admin form submits it) are the
shared fixtures — reuse them instead of hand-rolling admin, session and realm setup.

## Browser tests for client-only behavior

UI behavior that isn't about an endpoint's payload (interactions, client state, JS-only regressions) goes in
`tests/Browser`. They serve the built bundle, so run `npm run build` first, and restart `composer dev` after any
`lattice-php/*` dependency change — a running Vite dev server resolves the plugin registry once at start and will
otherwise serve a stale one. Adding stable `data-*` attributes to make an assertion clearer is fine.
