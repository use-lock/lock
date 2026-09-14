---
paths:
    - "vite.config.ts"
    - "resources/css/**"
    - "resources/js/**"
---

# Frontend build

## The Vite build needs vendor/ — Lattice plugins are discovered from composer

The `lattice()` Vite plugin discovers component packages from `vendor/composer/installed.json`. Building without
composer dependencies installed silently drops those packages' chunks: no build error, and pages using them render
nothing. Any CI job or script that runs `npm run build` must run `composer install` first.

## Tailwind must scan every Lattice package

`resources/css/app.css` sources `../../vendor/lattice-php/*/resources/js`. Lattice ships as several packages
(`core`, `ui`, `form`, `table`, `action`); narrowing that glob to one of them drops every class the others use and
breaks styling with no build error.

## Hand-written React is the exception

Pages, forms, tables, and actions are PHP classes rendered by the Lattice runtime. `resources/js/` holds only the
`registry.ts` plugin and the few components registered there by string key. A new UI need is a Lattice definition
first; reach for a custom React component only when the behavior genuinely cannot be expressed server-side.
