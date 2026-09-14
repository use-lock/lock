---
paths:
    - "app/*/Ui/Tables/**"
---

# Tables

## Registered context keys inherit through the entire component tree

Every registered key in a definition's frame cascades into child forms, row and bulk actions, fragments, slot
factories, and closure-built modals. Do not thread inherited keys such as `realm` through `Action::use()`,
`Form::use()`, or `Fragment::lazy()`. Pass only context that identifies the child definition itself, such as a row's
`member` key. A slot that starts a frame still sets its realm once with `->context(['realm' => $realm])`.

## Row actions are filtered by permission before they are built

Check the ability (`$user->can(...)`) around each `Action::use()` rather than relying on the action's own
authorization to hide it. The action still authorizes itself server-side; this keeps the rendered row honest.
