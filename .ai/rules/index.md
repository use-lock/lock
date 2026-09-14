# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

Every glob is a code span. Bare `*` in a table cell reads as markdown emphasis, and a formatter will rewrite
`app/*/Concerns/**` into `app/_/Concerns/\**` — silently breaking the mapping this file exists to carry.

| Applies to | Rule file |
| --- | --- |
| `app/*/Actions/**` | .ai/rules/actions.md |
| `app/*/*ServiceProvider.php` | .ai/rules/app.md |
| `app/**` | .ai/rules/architecture.md |
| `app/Shared/Audit/**`, `app/Audit/**` | .ai/rules/audit.md |
| `app/*/Concerns/**`, `app/*/Ui/Concerns/**` | .ai/rules/concerns.md |
| `database/data-migrations/**` | .ai/rules/data-migrations.md |
| `app/Admin/Enums/ManagementScope.php`, `app/*/Policies/**` | .ai/rules/enums.md |
| `resources/css/**`, `composer.json` | .ai/rules/css.md |
| `vite.config.ts`, `resources/css/**`, `resources/js/**` | .ai/rules/general.md |
| `resources/icons/**` | .ai/rules/icons.md |
| `app/*/Http/**` | .ai/rules/http.md |
| `database/migrations/**` | .ai/rules/migrations.md |
| `app/*/Models/**` | .ai/rules/models.md |
| `app/Auth/**`, `app/Admin/**`, `app/Realms/**`, `app/Clients/**`, `app/Roles/**`, `config/auth.php` | .ai/rules/realms.md |
| `routes/*.php` | .ai/rules/routes.md |
| `app/*/Services/**`, `app/*/Support/**`, `app/*/Data/**` | .ai/rules/services.md |
| `app/*/Ui/Tables/**` | .ai/rules/tables.md |
| `tests/**` | .ai/rules/testing.md |
| `lang/**` | .ai/rules/translations.md |
| `app/*/Ui/**` | .ai/rules/ui.md |
