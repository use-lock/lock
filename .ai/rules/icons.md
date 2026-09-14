---
paths:
    - "resources/icons/**"
---

# Icons

## logo.svg is referenced only from vendor config — do not delete as unused

resources/icons/logo.svg looks unreferenced (no app-code grep hit) but the use-lock/server package’s integrated UI renders the
login page brand via Icon::make(config('oidc-ui.brand_icon', 'logo')) — the 'logo' default lives in
vendor/use-lock/server/config/oidc-ui.php. Deleting the file silently drops the logo from the
login page. Sprite icons in this dir may be referenced by name from vendor defaults; check package configs before
treating one as dead.
