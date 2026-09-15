# Deployment

Lock ships as one Docker image (`ghcr.io/use-lock/lock`) that runs in three roles — web, queue worker, scheduler —
next to Postgres. `compose.yaml` describes that stack and is what Coolify (or plain `docker compose`) runs.
Staging and production are two separate deployments of the same image with their own database and environment; they
never share state.

## The image

Release Please maintains the release PR, starting at `0.1.0`. Merging that PR creates the GitHub release and
builds its exact tag into `ghcr.io/use-lock/lock:0.1.0`, `:0.1`, and `:latest`. Pre-releases do not move `latest`.
Pushes to `main` publish `:main`; every image also gets a `sha-<commit>` tag. The release workflow calls the image
build directly, so publishing works with `GITHUB_TOKEN` without a personal access token. Release PR checks are
started explicitly for the same reason.

- **Base: `serversideup/php:8.5-frankenphp`, serving through Laravel Octane in FrankenPHP worker mode.** The
  application boots once per worker and then serves many requests, so nothing request-specific may live in a
  singleton or a static property (see the `laravel-octane` guidelines). Octane clones the booted container for every
  request and drops it afterwards, so only services resolved during boot survive between requests: registries and
  caches of code-derived metadata, plus Lattice's closure evaluator, which is why a Lattice closure must not type-hint a
  request-scoped service (see `.ai/rules/ui.md`). Each worker is recycled after 500 requests (Octane's default `--max-requests`) as the
  last line of defence against leaks.
- The image brings its own entrypoint, PHP settings, and healthcheck scripts; the Dockerfile adds the `bcmath`, `gmp`,
  and `intl` extensions, sets environment defaults, and starts `php artisan octane:start`. Octane runs FrankenPHP with
  its own Caddyfile, not the image's. That Caddyfile sets no security headers, so `bootstrap/app.php` appends
  Laravel's `FrameGuard` globally: every response carries `X-Frame-Options: SAMEORIGIN`, which keeps the login pages
  out of other origins' frames.
- Multi-stage: `composer install --no-dev` → Vite build on Node 24 (the version CI uses; nothing in the repo pins
  one) → runtime image. The Vite stage needs `vendor/` because the Lattice Vite plugin discovers component packages
  from `vendor/composer/installed.json`. It runs `npm install` rather than `npm ci` for the same reason CI does: the
  lockfile is generated on macOS and lacks the Linux-only optional packages.
- Runs as the unprivileged `www-data` user from `/var/www/html` on port `8080`, plain HTTP. TLS terminates at the
  platform proxy; `bootstrap/app.php` already trusts every proxy (`trustProxies(at: '*')`), so there is no
  `TRUSTED_PROXIES` variable to set — narrow that in code if the container is ever reachable without a proxy in front.
- Every container start runs two entrypoint hooks before its command. The image's Laravel automations
  (`AUTORUN_ENABLED`) run `php artisan optimize`: config, events, routes, views, and the Lattice discovery manifest
  through its `optimize` hook; their own migration step is off. Then the Dockerfile's
  `/etc/entrypoint.d/60-app-deploy.sh` runs `php artisan app:deploy` when `AUTORUN_APP_DEPLOY=true`. A failed deploy
  stops the container before it serves.
- The default command serves HTTP. Any other command runs as-is after the hooks:

| Command                                                | Runs                                       |
| ------------------------------------------------------ | ------------------------------------------ |
| _(default)_                                            | `php artisan octane:start` (`app` service) |
| `php artisan queue:work --tries=3 --max-time=3600`     | the `queue` service                        |
| `php artisan schedule:work`                            | the `scheduler` service                    |
| `php artisan app:deploy`                               | the release step, when run by hand         |
| anything else, e.g. `php artisan admin:grant me@x.com` | executed as-is                             |

## Environment

Everything is passed as environment variables; there is no `.env` inside the image. `compose.yaml` wires the
service-internal values (`DB_HOST=postgres`, database and user `lock`) and exposes what differs
per deployment as `${VAR}`, so Coolify can present them as deployment variables. The image sets the production
defaults every deployment shares. `APP_ENV` and `APP_DEBUG` are left to the framework defaults, `production` and
`false`; cache, cache locks, sessions, and the queue use the framework's `database` drivers.

| Variable                                               | Default                               | Notes                                                                                                                                                                                                                                                                                                  |
| ------------------------------------------------------ | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP_KEY`                                              | required                              | `docker run --rm ghcr.io/use-lock/lock php artisan key:generate --show`. Rotating it invalidates sessions and every encrypted value, signing keys included.                                                                                                                                            |
| `APP_URL`                                              | required                              | Public HTTPS origin, no trailing slash. It is the host of the console and of the master realm, whose issuer it is; every other realm is served from its own domain with the same scheme (see [Realm domains](#realm-domains)). It is baked into signed URLs and the first-party client's redirect URI. |
| `DB_PASSWORD`                                          | required                              | Postgres password; the compose file feeds the same value to the `postgres` service.                                                                                                                                                                                                                    |
| `MAIL_FROM_ADDRESS`                                    | required                              | Sender of verification, password reset, and invitation mails. The sender name is `APP_NAME`.                                                                                                                                                                                                           |
| `MAIL_URL`                                             | empty                                 | The SMTP connection as one URL, e.g. `smtp://user:password@mail.example.com:587` (STARTTLS) or `smtps://…:465` (implicit TLS). URL-encode special characters in the credentials. Without it no mail goes out.                                                                                          |
| `MAIL_MAILER`                                          | `smtp`                                | The app's own default in `config/mail.php`, so `compose.yaml` does not set it. Add `MAIL_MAILER: log` to the `environment` block to write mails to the log stream instead; the local trial overlay does.                                                                                               |
| `LOCK_CONSOLE_CLIENT_ID`, `LOCK_CONSOLE_CLIENT_SECRET` | empty                                 | The console's own OIDC client. Pick both values yourself; every deploy provisions or re-provisions exactly that client. Leave either empty and the client is left as it is.                                                                                                                            |
| `LOCK_ADMIN_EMAIL`, `LOCK_ADMIN_PASSWORD`              | empty                                 | The first administrator, created in the master realm, verified, and holding Super Admin. The password is re-applied on every deploy; leave it empty to manage the password in the console instead.                                                                                                     |
| `SENTRY_LARAVEL_DSN`                                   | empty                                 | Sentry project DSN. Empty means the SDK is inert: no errors, traces, or logs leave the container.                                                                                                                                                                                                      |
| `SENTRY_ENVIRONMENT`                                   | `APP_ENV` (`production`)              | What separates staging from production in Sentry, since both run as `production`. Set it to `staging` on the staging deployment.                                                                                                                                                                       |
| `SENTRY_RELEASE`                                       | empty                                 | The release the events belong to. Use the same `sha-<commit>` the image is pinned to, so a regression points at a commit.                                                                                                                                                                              |
| `SENTRY_TRACES_SAMPLE_RATE`                            | `0.1`                                 | Share of requests, queue jobs, and commands traced for performance. `0` turns tracing off; errors still go out.                                                                                                                                                                                        |
| `SENTRY_ENABLE_LOGS`                                   | `false`                               | Forwards log records at `LOG_LEVEL` and above to Sentry through the `sentry_logs` channel the image already has in `LOG_STACK`.                                                                                                                                                                        |
| `AUTORUN_APP_DEPLOY`                                   | `false`; `true` on the `app` service  | Runs `php artisan app:deploy` on container start, before the server. `compose.yaml` sets it on `app` only; `queue` and `scheduler` wait for `app` to be healthy, so they start against a migrated schema.                                                                                              |
| `LOCK_IMAGE`                                           | `ghcr.io/use-lock/lock:latest`        | Not a container variable: the image tag the stack runs. Pin it to a `sha-<commit>` tag (see [Staging versus production](#staging-versus-production)).                                                                                                                                                  |
| `APP_NAME`                                             | `Lock`                                | Set in the image. Names the session cookie and the mail sender.                                                                                                                                                                                                                                        |
| `DB_CONNECTION`                                        | `pgsql`                               | Set in the image. The framework also reads it for the failed-jobs and job-batches tables.                                                                                                                                                                                                              |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`                | `stack`, `stderr,sentry_logs`, `info` | Set in the image. Every log line goes to the container output, and to Sentry once `SENTRY_ENABLE_LOGS` is on. Drop `sentry_logs` from `LOG_STACK` to keep logs out of Sentry whatever the SDK is told.                                                                                                 |
| `SESSION_SECURE_COOKIE`                                | `true`                                | Set in the image. Cookies are only ever sent over HTTPS (browsers exempt `localhost`).                                                                                                                                                                                                                 |
| `OCTANE_SERVER`                                        | `frankenphp`                          | Set in the image, so `octane:status` and `octane:reload` address the running server.                                                                                                                                                                                                                   |

Any other Laravel or app setting (`SESSION_LIFETIME`, `LOCK_USER_EVENT_RETENTION_DAYS`, `LOCK_ADMIN_NAME`, …) keeps
its default until you add it to the `environment` block. The console signs in against the master realm `admin` with
the first-party client's credentials, which is what self-SSO wants; only to sign it in against another provider, add
`OIDC_RP_ISSUER`, `OIDC_RP_CLIENT_ID`, and `OIDC_RP_CLIENT_SECRET`.

### Realm domains

Every realm is served from a host of its own: the master realm from the host in `APP_URL`, every other realm from the
domain given when it is created (**Realms → Create realm**). The host is the realm's issuer, so the browser keeps
each realm's session apart by origin. Leave `SESSION_DOMAIN` unset: a cookie scoped to a parent domain would be shared
by every realm below it.

For a realm's domain to reach the instance:

1. Point its DNS record (`A`/`AAAA`, or a `CNAME` to the `APP_URL` host) at the platform.
2. Add it to the domains of the `app` service in Coolify, next to `APP_URL`'s host, so the proxy routes it to the
   container and requests its TLS certificate.

Lock checks a domain when the realm is created, when its domain changes, and on **Check again** in the realm's
settings: it requests `https://<domain>/.well-known/lock/domain-check` and expects the answer only this instance
(keyed with `APP_KEY`) gives, without following redirects. A realm whose check fails is created anyway and marked
_Unreachable_ (no answer: DNS, proxy entry, or certificate missing) or _Wrong server_ (something else answers). The
container has to reach its own public domains for the check to pass. A request for any host that is neither
`APP_URL`'s nor a realm's is answered with `404`.

Changing a realm's domain changes its issuer: every client of the realm has to follow, existing sessions stay behind
on the old host, and passkeys, being bound to the host they were created on, have to be registered again.

## First deploy

Nothing is provisioned by hand and nothing is written inside the container: the environment carries the console's
credentials and the first administrator, and `app:deploy` brings the database in line with them.

1. Provide `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `MAIL_URL`, and `MAIL_FROM_ADDRESS`, plus the four bootstrap variables. Choose the
   client id and secret and the administrator's address and password yourself — the infrastructure code that
   provisions the instance already holds them, and they are what it authenticates with afterwards:

    ```bash
    LOCK_CONSOLE_CLIENT_ID=lock-console
    LOCK_CONSOLE_CLIENT_SECRET=<generated secret>
    LOCK_ADMIN_EMAIL=you@example.com
    LOCK_ADMIN_PASSWORD=<generated password>
    ```

    The password has to satisfy the master realm's password policy, or the deploy fails with the rule it violated.

2. Start the stack (`docker compose up -d`, or deploy in Coolify). The `app` container runs `app:deploy` before its
   server starts: `migrate --force`, the data migrations (creates the master realm), `oidc:rotate-keys --if-missing` for every
   realm, `app:bootstrap`, `lattice:discover-cache`, and `scramble:cache`. Every step is idempotent and safe to
   repeat, so the master realm's signing key exists from this point on and no key material goes into the environment
   (details under [Signing keys](#signing-keys)). `queue` and `scheduler` start once `app` is healthy. `/up` answers
   `200` as soon as the server is up; it does not probe the database. To repeat the release step by hand:
   `docker compose run --rm app php artisan app:deploy`.

3. Sign in at `APP_URL` with `LOCK_ADMIN_EMAIL` and `LOCK_ADMIN_PASSWORD`. The console client is already provisioned,
   trusted, and recorded as the master realm's first-party client, so the login skips the consent screen.

That is the whole first deploy. Every later release is the same: pull the new image and recreate the services; the
new `app` container deploys before it serves. Coolify needs no pre-deployment command. The complete workflow holds
one database session advisory lock on PostgreSQL or MySQL/MariaDB, including the first migration on an empty database.
SQLite uses a file lock next to its database. A competing deployment fails before running any step and must be retried
after the active deployment finishes. Run deployments against the database writer directly; PostgreSQL transaction-pooling
proxies and multi-writer database clusters are not supported by the session lock.

### The environment wins, on every deploy

`app:bootstrap` (the step `app:deploy` runs, also runnable on its own) reconciles rather than installs:

- **Every variable is optional, and unset means unmanaged.** No `LOCK_CONSOLE_CLIENT_SECRET` and the client is left
  exactly as it is; no `LOCK_ADMIN_PASSWORD` and an existing administrator's password is not touched; no
  `LOCK_ADMIN_EMAIL` and no user is created. That is what makes the step safe to run on every release.
- **What is set is applied, every time.** A client secret changed in the environment is written to the client on the
  next deploy, and **an administrator password left in the environment is re-applied on every deploy** — a password
  changed in the console is overwritten by the next release. Rotate by changing the variable, or clear the variable
  once the administrator manages their own password.
- **The administrator is created whenever the address is missing** from the master realm, however many other users
  exist. It is not a first-deploy-only step.
- **It converges.** Re-running creates nothing twice: the console client is matched by its provisioning key and then
  by its id, and the administrator by their address in the master realm. The command prints `created`, `updated`,
  `unchanged`, or `skipped (unset)` per item and never prints a secret.

The console client carries the `authorization_code`, `refresh_token`, and `client_credentials` grants: the same
credentials that sign an administrator in interactively are the ones an automated caller uses to get a token from
`APP_URL/oauth/token`. Treat `LOCK_CONSOLE_CLIENT_SECRET` as a production secret.

### Without the bootstrap variables

Leaving `LOCK_CONSOLE_CLIENT_SECRET` empty keeps the manual path open. Provision the client once and set
`OIDC_RP_CLIENT_ID` and `OIDC_RP_CLIENT_SECRET` from the output, then add the client id
to the master realm's **Trusted clients** in the console so the login skips consent:

```bash
docker compose run --rm app php artisan oidc:client --first-party --trusted \
    --name="Lock Console" \
    --redirect-uri="$APP_URL/login/callback" \
    --post-logout-redirect-uri="$APP_URL"
```

A plain re-run creates a second client; re-run with `--adopt=<client_id>` to update the existing one, and add
`--rotate` to issue a new secret. Register the first administrator on the master realm's registration page
(`APP_URL/auth/register`), verify the address, then grant the global role:

```bash
docker compose run --rm app php artisan admin:grant you@example.com
```

Locally, `composer setup` runs `app:setup`, which generates missing console credentials in `.env` and delegates
provisioning to `app:deploy`. Repeating setup preserves existing keys and credentials. `composer fresh` resets the
database, runs local setup, then adds the non-admin `demo@example.com` account. `db:seed` only adds demo data;
it refuses production environments and never writes `.env`.

## Realms and SaaS clients

The SaaS uses two realms, `staging` and `production`, next to the master realm `admin`. Create them in the console
(**Realms → Create realm**) with exactly those slugs and a domain each (see [Realm domains](#realm-domains)). Creating
a realm mints its signing keypair. Each realm then exposes its own issuer, discovery document, and JWKS on its domain:

```text
https://auth.staging.saas.example/.well-known/openid-configuration
https://auth.staging.saas.example/.well-known/jwks.json
https://auth.saas.example/.well-known/openid-configuration
```

Clients belong to the realm they are created in. Create the SaaS’s client under **Clients** in the target realm’s
admin console and register its callback URL (for example, `https://staging.saas.example/auth/callback`). Copy the
client secret when it is shown; it cannot be retrieved later. `php artisan oidc:client` provisions the master
realm’s first-party console client.

Point the SaaS's staging environment at the `staging` realm issuer and its production environment at the
`production` realm issuer. Whether both realms live on one Lock deployment or the staging realm lives on a separate
staging Lock (to rehearse Lock upgrades themselves) is a topology choice; the compose file supports either.

## Signing keys

Keys live in the `oidc_signing_keys` table, never in the environment. There is **one keypair per realm**: the realm
is the issuer, so a realm signs with its own key and publishes only that key at its own JWKS endpoint.

- **Creation is part of the deploy.** `app:deploy` runs `oidc:rotate-keys --if-missing` for every realm, so the
  master realm has a key from the first deploy on. A realm created later in the console gets its keypair when it is
  created; the deploy step is the safety net for a realm that somehow has none.
- **Rotation is `oidc:rotate-keys`** (no flag), run with the target realm current:

    ```bash
    docker compose run --rm app php artisan tinker --execute '
    App\Realms\Models\Realm::query()->where("slug", "admin")->sole()
        ->runAsCurrent(fn () => Illuminate\Support\Facades\Artisan::call("oidc:rotate-keys", ["--force" => true]));
    '
    ```

    The new key signs from that moment; the previous one is retired but stays in the realm's JWKS under its own
    `kid`, so tokens it signed keep verifying. Delete the retired row once every token signed by it has expired
    (access-token lifetime plus the refresh window). No restart is needed — nothing is cached in the environment.

- **The private key is encrypted at rest with `APP_KEY`** (the model casts it as `encrypted`). Losing `APP_KEY`
  therefore loses every signing key along with the sessions and other encrypted values, and every realm has to be
  re-keyed with `oidc:rotate-keys`, which invalidates every outstanding token and ID token.
- **Backup is the database backup.** There is nothing else to keep — but the dump is only usable together with the
  `APP_KEY` that was current when it was taken.

## Database backups

Postgres is the only state that matters: users, realms, clients, signing keys, tokens, sessions, consents,
roles, settings, and the audit log all live there. Back up the `postgres` volume (or run `pg_dump` from the
`postgres` service) on a schedule. The `storage` volume only holds uploaded files, of which there are none today.

Cache, cache locks, sessions, and the queue all use Laravel's `database` drivers, the framework defaults, so the stack
needs no Redis. Sessions have to be rows anyway: the account page lists a user's browser sessions and signs single
ones out.

## Monitoring

- **Logs** go to the container output: Octane's server output (warnings and above outside `local`) and Laravel's
  `stderr` channel; let the platform collect them.
- **Errors, traces, and logs in Sentry**: `SENTRY_LARAVEL_DSN` turns the SDK on, and nothing before it. Unhandled
  exceptions are reported from `bootstrap/app.php`, requests, queue jobs, and commands are traced at
  `SENTRY_TRACES_SAMPLE_RATE` (`/up` is excluded), and `SENTRY_ENABLE_LOGS=true` forwards the log stream as well.
  The SDK sees Octane's worker lifecycle, so a request's scope does not leak into the next one. Personal data —
  the signed-in user, the client IP, request bodies — is left out unless `SENTRY_SEND_DEFAULT_PII=true`.
- **Health**: `GET /up` on port 8080 (the image's Docker `HEALTHCHECK`, pointed there by `HEALTHCHECK_PATH`). The
  `queue` and `scheduler` services override it with the image's `healthcheck-queue` and `healthcheck-schedule`, which
  check that the worker process is running.
- **Login failures, token issuance, admin changes**: authentication events are stored in `user_events` and
  administrator changes in `admin_events`, browsable through the console’s event views. Retention is controlled by
  `LOCK_USER_EVENT_RETENTION_DAYS` and `LOCK_ADMIN_EVENT_RETENTION_DAYS`; the scheduler runs `model:prune`
  daily alongside `oidc:prune` and `lattice:notifications:prune`. `oidc:dispatch-expired-session-logouts` runs hourly.
- **Discovery**: probe `https://<realm domain>/.well-known/openid-configuration` and `.well-known/jwks.json` per realm;
  a `500` on JWKS means the realm has no signing key (run `app:deploy` again) or `APP_KEY` no longer decrypts it.

## Staging versus production

Run them as two independent stacks — separate Coolify projects (or compose project names), separate Postgres,
separate `APP_KEY`, separate `APP_URL`. Signing keys follow the database, so they are separate by
construction. Only the image is shared: pin staging to the
`sha-<commit>` tag you are about to promote, and production to the same tag once staging is verified. Nothing but
those values distinguishes the two, so a staging stack can be recreated from scratch at any time.

## Local trial

```bash
docker compose --env-file compose.local.env -f compose.yaml -f compose.local.yaml up -d --pull always --wait
```

The local overlay uses the public `ghcr.io/use-lock/lock:latest` image from [compose.yaml](../compose.yaml)
and publishes port `8080`. No local image build is required. The `app` container deploys on start. Then open `http://localhost:8080/.well-known/openid-configuration`
and its `jwks.json`, which the deploy step has already keyed, and sign in at `http://localhost:8080` with the `LOCK_ADMIN_EMAIL` and `LOCK_ADMIN_PASSWORD`
from `compose.local.env`. The same file's client id and secret get a machine token:

```bash
curl -s -X POST http://localhost:8080/oauth/token \
    -d grant_type=client_credentials -d client_id=lock-console -d client_secret=local-console-secret
```

A realm created in the trial can take `<slug>.localhost` as its domain: browsers resolve every `*.localhost` to the
loopback address, and the realm shares `APP_URL`'s port, so it is served at `http://<slug>.localhost:8080`.

Every value in `compose.local.env` is a public throwaway; never reuse it.
