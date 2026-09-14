---
paths:
    - "app/Shared/Audit/**"
    - "app/Audit/**"
---

# Audit

## Shared holds the seam, the Audit domain holds everything else

`app/Shared/Audit` is only what another domain needs to record something: the `Audit::record()` entry point, the
`AdminActionPerformed` event it raises, the `AdminEventType` contract each domain's enum implements (with
`IsAdminEventType`), and `AdminEventCategory`. The models, the listener, the sink, the type registry and the UI live
in `app/Audit`. Nothing outside `app/Audit` queries `AdminEvent` or `UserEvent`.

A domain's own vocabulary — `ClientAdminEvent`, `UserAdminEvent`, `RoleAdminEvent`, `ResourceAdminEvent`,
`RealmAdminEvent` — stays in `app/{Domain}/Enums` as public vocabulary, because the domain writes it and `app/Audit` reads it back
for filter options through `App\Audit\Support\AdminEventTypes`.

## Two trails: admin_events for what an administrator changed, user_events for what the OIDC server raised

Both tables carry the same provenance — `realm_id`, `type`, `category`, `user_id`, `sid`, `ip`, `user_agent`,
`context`, `occurred_at` — so a row of either reads the same way. `admin_events` adds the polymorphic subject it was
performed on; `user_events` adds `client_id` and `failure`. Do not merge them: security events arrive at a far higher
volume, carry no subject, and outlive the user and realm they name (which is why neither table has a foreign key).

What happens inside a realm is recorded with the realm so it lands on that realm's trail; creating and deleting a
realm, and the user administration events, carry no realm and make up the instance trail (`AdminEvent::global()`).

`type` stays a plain string in both tables — a package update may add a type the enums do not know yet — and the
label is translated at read time from `audit.admin.types.*` / `audit.user.types.*`, so a row renders in the viewer's
locale rather than the writer's.

Retention is `config('lock.events.*_retention_days')`, enforced by `model:prune` in `routes/console.php`.

## Recording is one event and one listener

`Audit::record()` only raises `AdminActionPerformed`; `App\Audit\Listeners\RecordAdminEvent` writes the row and is
the one place that stamps actor, ip, user agent and sid. It resolves request and session state per event rather than
capturing it, so it stays safe under Octane. Unlike the security trail it is fail-closed: it runs inside the
transaction of the action that raised the event, so a row that cannot be written takes the change down with it.
`Audit::withoutRecording()` drops everything a callback records — for establishing a baseline, never for an
administrative act.

Anything else that has to react to administration listens to the same event rather than being called from an action.

## The sink

`App\Audit\Support\DatabaseAuditSink` writes what the OIDC server raises to `user_events`. It is named by
`config('oidc.audit.sink')`. `mergeConfigFrom` merges only the top level, so `config/oidc.php` has to repeat the
package's whole `audit` block (`enabled`, `sink`, `log_channel`), not just the key it overrides. The package's
listener swallows whatever the sink throws, so a failure here is silent — a broken sink shows up as missing rows,
never as a failed login.

The realm is stamped from `Realm::current()` at write time, not from the record: the realm use-lock/server serves,
which for a console command that names none (provisioning, key rotation) is the master realm.
