---
paths:
    - "app/*/Ui/**"
---

# Lattice UI

## The Ui slice is the only place that may touch Lattice

`app/{Domain}/Ui/**` is the Lattice adapter layer. Everything outside it — models, actions, policies, support
classes — must not import `Lattice\…` or another domain's `Ui\…`. `tests/Feature/ArchitectureTest.php` checks every
child of a module except its `Ui` slice and service provider.

## The admin console is the only management UI

Realms, their configuration and their users are managed from the `/admin/…` console by users of the master realm; a
realm user gets account pages, never a console. A module that wants to contribute UI to a page it does not own
hangs it into a `Slot` from its provider with `Lattice::extend(…, priority:)` instead of importing the page.

Its shell — `App\Shared\Ui\Layouts\AdminLayout` and the `App\Shared\Ui\Pages\AdminPage` base class — lives in
`app/Shared/Ui/**` so any domain may contribute a console page. A domain owns the pages for its own records
(`app/Realms/Ui/**` for the realm record, `app/Clients/Ui/**` for its OAuth clients, `app/Resources/Ui/**` for its
protected resources, `app/Auth/Ui/**` for its users, `app/Roles/Ui/**` for its roles,
`app/Audit/Ui/**` for the trail and the security events); the pages whose domain has no
`Ui` slice yet stay in `app/Admin/Ui/**`.

The shell owns no navigation of its own. Its sidebar is two slots a domain fills from its provider —
`admin.sidebar.realm`, which carries the selected `Realm` in its context, and `admin.sidebar.instance` — and
`admin.users.detail.cards` is how the roles card reaches the user page. Order is the `priority:` argument, and an
entry gates itself with `MenuItem::…->can(ManagementScope::…)` rather than the shell testing the user. Because the module
registering an entry owns the page, it builds it with `MenuItem::fromPage()`; only the realm switcher is built in
the shell, and it links by route name because importing `App\Realms\Ui\Pages\…` there would make the shared layer
depend on a domain.

The console is Keycloak-shaped: a realm-scoped page lives under `/admin/realms/{realm}/…` and takes the bound
`Realm $realm` as a render parameter; `AdminLayout` reads that route parameter to decide which of the two sidebar
slots it renders below the realm switcher.
Instance-wide pages stay directly under `/admin/…`. There is no cross-realm user list on purpose.

A definition's id is the endpoint every client already calls, so it does not follow the class between namespaces:
the realm definitions moved to `App\Realms\Ui` keep their `admin.realms.*` ids.

## Resolve models through registered context, never route parameters or session state

`RealmsServiceProvider` registers `realm` and its dependent model keys with `Lattice::context()`. Definitions use
`$this->contextModel('realm', Realm::class)` when the key is required and
`$this->contextModelOrNull('realm', Realm::class)` when render-time authorization should hide a component with missing
context. The class types the result, so no `@var` or `instanceof` guard follows the call. Use `$this->context('realm')` only when the raw scalar
is genuinely needed; do not query from `contextString()` or read `$request->route()` inside a definition. Signed
definition endpoints may run after the page request, so the registered context remains the source of truth.

Give closure resolvers a concrete model return type. Lattice uses it to map bound route models to registered context
keys. The `realm` resolver in `RealmsServiceProvider` is a plain lookup: every realm definition gates itself on an
`ManagementScope`, so the slug the client round-trips on lazy table and form requests cannot reach data the admin
may not see anyway. A resolver for data that is not admin-only has to check the signed-in user itself.

Context resolvers are pure lookups. A resolver also runs while components are merely being built, once per distinct
value, so a side effect in it (setting a locale, writing an audit row) fires for records the request is not
about. Request-wide state keyed to the context belongs in `Definition::activated()`, which Lattice runs once per
signed endpoint after the gate has passed; a page has no `activated()`.

Declare simple authorization on the definition attribute:
`#[AsForm('admin.realms.update', can: ManagementScope::RealmsWrite)]`. With a registered
subject key (`on: 'realm'`) Lattice resolves the subject before the definition runs and denies a missing subject. Keep `authorize()` only for conditions a gate
subject cannot express, and use the nullable context accessor inside it.

## Reach for the Lattice skills before writing a definition

`lattice-forms`, `lattice-tables`, `lattice-actions`, and `lattice-closures` cover the builder APIs, the validation
and effect models, and how closure parameters resolve. Read the matching one rather than inferring the API from a
sibling class.

## Regenerate TypeScript after changing a wire type

A `#[AsComponent]` / `#[AsField]` / `#[AsColumn]` class is part of the wire format. After changing its public
properties run `php artisan lattice:typescript` and commit `resources/js/lattice/generated.d.ts`. The generator
reads `extra.lattice.discover` from `composer.json` and fails loudly when discovery is empty. The app currently
declares none, so the `js` workflow only runs the generator once one exists — adding the first wire type arms that
check, and the generated file has to be committed with it.
