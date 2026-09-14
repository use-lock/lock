---
paths:
    - "app/*/*ServiceProvider.php"
---

# Service providers

## A domain's provider lives in the module root, not in Providers/

`app/{Domain}/{Domain}ServiceProvider.php` — e.g. `app/Realms/RealmsServiceProvider.php`. There is no
`app/{Domain}/Providers/` subfolder: a module has exactly one provider, and a directory holding a single file only
adds a level to read past. `App\Shared\AppServiceProvider` is the app-wide one. Register new providers in
`bootstrap/providers.php`.

## The provider is where a module wires itself into the app

Context resolvers (`Lattice::context('realm', …)`), slot extensions
(`Lattice::extend('app.user-menu', …)`), container singletons, gate hooks, and middleware appended to a group
belong here — that keeps a module's entry points in one file instead of scattered across the slice it happens to
touch. Register dependent context keys beside their parent domain resolver and resolve the parent through
`ContextResolutions`. The architecture test's no-Lattice rules skip service providers, so importing `Lattice\…` here
is allowed.
