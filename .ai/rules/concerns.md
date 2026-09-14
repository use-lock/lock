---
paths:
    - "app/*/Concerns/**"
    - "app/*/Ui/Concerns/**"
---

# Concerns

## Cross-cutting behavior is a trait in Concerns/

Share small cross-cutting behavior across models or other classes via a trait in `app/{Domain}/Concerns/`, not
inheritance. Traits that exist only to compose Lattice UI (rendering a menu, a fragment body) live in
`app/{Domain}/Ui/Concerns/` instead, so the domain slice stays free of the UI layer.
