# Lock

An OpenID Connect identity provider built on Laravel — a Keycloak-style IdP with a server-driven
admin console rendered through [Lattice](https://latticephp.com). Pages, forms, tables, and layouts
are defined in PHP and rendered as React components over [Inertia](https://inertiajs.com).

## Run with Docker

The [Docker Compose stack](compose.yaml) runs Lock's web server, queue worker, scheduler, and PostgreSQL,
with persistent volumes for the database and application storage. All three Lock services use the public
`ghcr.io/use-lock/lock:latest` image, which includes PHP dependencies and frontend assets. You only need
Docker with Compose; no registry login or local image build is required.

### Try It Locally

Clone the repository and start the stack with the [local Compose overlay](compose.local.yaml):

```bash
git clone https://github.com/use-lock/lock.git
cd lock
docker compose --env-file compose.local.env -f compose.yaml -f compose.local.yaml up -d --pull always --wait
```

Open `http://localhost:8080` and sign in with **admin@localhost** / **LocalAdminPassword1**.
Startup runs migrations, generates signing keys, and provisions the console client and administrator.
Emails are written to the container logs.

The credentials in [compose.local.env](compose.local.env) are public and intended only for this local trial.

### Deploy the Published Image

The [Compose file](compose.yaml) defaults to `ghcr.io/use-lock/lock:latest`. Set `LOCK_IMAGE` to choose
another published tag. Releases publish version tags alongside `latest`; builds from the main branch
use `ghcr.io/use-lock/lock:main`. For reproducible deployments, pin a published version or `sha-<commit>` tag.

Configure these variables in your deployment environment or a Compose environment file:

| Variable                                               | Purpose                                    |
| ------------------------------------------------------ | ------------------------------------------ |
| `APP_KEY`                                              | Persistent Laravel encryption key.         |
| `APP_URL`                                              | Public HTTPS origin of your Lock instance. |
| `DB_PASSWORD`                                          | PostgreSQL password.                       |
| `MAIL_FROM_ADDRESS`, `MAIL_URL`                        | Sender address and SMTP connection URL.    |
| `LOCK_CONSOLE_CLIENT_ID`, `LOCK_CONSOLE_CLIENT_SECRET` | Credentials for the console's OIDC client. |
| `LOCK_ADMIN_EMAIL`, `LOCK_ADMIN_PASSWORD`              | Initial administrator credentials.         |

With these values in `compose.env`, start the stack:

```bash
docker compose --env-file compose.env -f compose.yaml pull
docker compose --env-file compose.env -f compose.yaml up -d --wait
```

Place the app service behind an HTTPS reverse proxy that forwards to container port `8080`.
The production Compose file does not publish a host port. The app applies migrations on startup;
the queue worker and scheduler wait until it is healthy.

See the [deployment guide](docs/deployment.md) for key generation, image tags, environment settings,
realm domains, backups, and Coolify setup.

## Features

- **OpenID Connect provider** — authorization code + PKCE, token exchange, consent, discovery, and
  key rotation via [`use-lock/server`](https://github.com/use-lock/server), which owns the OAuth2 core
  and its client and token storage.
- **Relying-party login** — the app signs its own users in through its own OIDC endpoints
  (self-SSO) through `use-lock/client-laravel`, so the account console runs on the same flows external clients use.
- **Authentication** — registration, password reset, email verification, **two-factor** (TOTP,
  recovery codes) and **passkeys**, with the Lattice auth UI integrated into `use-lock/server`.
- **Realms** — the identity boundary, Keycloak-style. A user belongs to exactly one realm, an address identifies
  a different person in each, and every realm has its own OIDC issuer, clients and authentication settings.
- **Admin console** — the `admin` realm's first-party UI: realm CRUD and configuration, user administration,
  global roles, and a cross-realm activity view.
- **Audit logs** — separate trails for administrator changes and realm authentication events, with configurable retention.
- **i18n** — English and German out of the box with enforced key parity.
- **Modern frontend** — React 19, Inertia 3, Tailwind CSS v4, TypeScript.

## Local Development

To work on the source outside Docker, install PHP 8.5+, Composer, Node.js 22+, and
[Laravel Herd](https://herd.laravel.com), then run:

```bash
composer setup
composer dev
```

Setup installs dependencies, creates `.env`, generates local credentials and signing keys, runs migrations,
provisions the console client and first administrator, and builds the frontend. The development command
runs the queue, logs, and Vite while Herd serves the app.

The local development administrator is **test@example.com** / **password**.
`composer fresh` resets the local database, provisions the application, and adds a non-admin demo user.
`php artisan db:seed` adds demo data only; deployment never seeds demo accounts.

## Testing

```bash
composer ci:check    # lint, format and type checks, PHPStan, Rector, then Pint + the Pest suite
```

## License

MIT.
