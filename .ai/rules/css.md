---
paths:
    - "resources/css/**"
    - "composer.json"
---

# Css

## The Lattice Vite plugin wires discovered package styles

`@import '@lattice-php/lattice/css'` includes the stylesheets and Tailwind sources declared by every discovered
component package. Do not add package-specific imports or `@source` entries for those packages. Keep the
`@source '../../vendor/lattice-php/*/resources/js'` glob for the core UI, form, and table packages, which are not
component packages discovered through Composer.
