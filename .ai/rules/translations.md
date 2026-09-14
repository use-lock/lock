---
paths:
    - "lang/**"
---

# Translations

## Kebab-case keys only

All translation keys use lowercase letters and dashes. Never underscores or camelCase —
`realm.settings.members.invited`, not `realm.settings.members_invited`.

## Dot notation via nested arrays

Use nested PHP arrays to build dot-separated keys: `'settings' => ['general' => ['heading' => '...']]` resolves to
`team.settings.general.heading`.

Dots express hierarchy. Use `actions.delete.confirm.title`, not `actions.delete-confirm-title`, and
`fields.name.placeholder`, not `fields.name-placeholder`. Keep dashes for compound terms and stable identifiers
such as tab keys and persisted audit event names. A guard in `tests/Feature/I18n/TranslationKeysTest.php`
fails on flat structural suffixes.

When a key needs child metadata, move its original value to `.label`. Use `.default` for the primary value of a
grouped message, such as `state.past-due.message.default` alongside `state.past-due.message.restricted`.

## Suffixes for secondary strings

`.label` for form labels, `.help-text` for helper text, `.title` for notification titles — nested under the field
key when a field carries both a primary string and secondary text.

## common.* for reusable strings

Shared field labels (`common.field.email-address`), actions, and statuses live in `lang/{locale}/common.php`.

## File naming

Translation files use kebab-case filenames matching the area (`user.php`, `realm.php`, `auth.php`).

## Always update both locales

Every addition or change lands in `lang/en/` **and** `lang/de/`. Key parity is enforced by
`tests/Feature/I18n/TranslationKeysTest.php`.
