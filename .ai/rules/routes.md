---
paths:
    - "routes/*.php"
---

# Routes

## routes/web.php stays minimal — Lattice self-registers page routes

Lattice pages register themselves via `#[AsPage(route: …)]`, so `routes/web.php` only carries non-Lattice endpoints
(OAuth callbacks, one-off redirects such as the home route). `routes/api.php` is a regular routes file and is not
held to this — declare its endpoints there normally.

## Middleware is attached at the route or group level

Assign middleware via `->middleware()` on the route or group, or via `#[AsPage(middleware:)]` — never inside a
controller.
