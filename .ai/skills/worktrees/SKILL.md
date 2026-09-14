---
name: worktrees
description: Use when creating, preparing, listing, switching between, or removing git worktrees for the Lock app, especially when multiple agents work in parallel. Covers safe multi-agent branch isolation, the sibling worktree layout, full-app dependency/env setup, the required verification gates, and cleanup.
---

# Lock Worktrees

Lock is a **full Laravel 13 + Inertia + React + Lattice app** (served by Herd at `http://lock.test`), not a
package. A worktree therefore needs its own dependencies, `.env`, app key, database, and built assets.

Use a worktree whenever you need to make changes while other agents may be editing the main checkout or another
branch. Keep each agent's work isolated and never move, reset, delete, or overwrite another agent's files.

## Layout: siblings of the main checkout

Worktrees live **as siblings of the repo**, directly under its parent directory, named `lock-<slug>`:

```text
<parent>/lock/
  lock/                     # main checkout (repo root)
  lock-<slug>/              # a worktree
  lock-<other-slug>/        # another worktree
```

Do **not** nest worktrees inside the repo (no `.worktrees/`). Siblings sit outside the working tree, so they need no
`.gitignore` entry and never pollute `git status`.

## First: take stock of existing worktrees

Before creating anything, list what exists and clean up if it has grown:

```bash
git worktree list
git status --short
git branch --show-current
```

Rules:

- **If there are already many worktrees (roughly 5+), do not silently add another.** Tell the user which look finished
  (branch merged, work done) and should be closed first:
    ```bash
    git branch --merged main          # branches already merged — their worktrees can usually go
    git worktree list --porcelain     # match branches back to worktree paths
    ```
- Recommend closing; never remove another agent's worktree yourself.
- Treat every uncommitted change you did not make as another agent's work — do not clean, stash, reset, checkout, or
  remove it.
- Pick a unique slug and branch, usually `<task>-<short-slug>`. Don't reuse an existing path/branch unless asked.

## Create and set up

Run from the repo root — one script does the whole thing:

```bash
bin/create-worktree.sh <slug> [branch]      # branch defaults to <slug>
```

It creates `../lock-<slug>` branched off `main`, gives it a Herd site
(`https://lock-<slug>.test`) with `APP_URL` pointed at it, then installs composer and npm
dependencies, generates the app key and the OIDC signing keys, migrates a fresh SQLite database, syncs the
scope catalog, and builds the frontend. It refuses to touch an existing directory.

Examples:

```bash
bin/create-worktree.sh audit-export feat/audit-export
bin/create-worktree.sh token-fix fix/token-fix
```

Notes:

- The script always branches off `main`. To continue an existing branch, do it by hand
  (`git worktree add ../lock-<slug> <branch>`) and run the setup steps yourself.
- It uses `npm ci`, not `npm install` — `install` rewrites `package-lock.json` with the platform's optional
  dependencies, which would leave every fresh worktree dirty.
- `composer install` runs `boost:update` via its hooks, regenerating the git-ignored `CLAUDE.md` / `AGENTS.md` so the
  worktree has agent context. If they look stale, run `php artisan boost:update` on demand.
- Seed the demo user and a personal-access client with `php artisan db:seed` if your work needs them.

## Verify

Run the gates that match what you changed before reporting work — they mirror CI:

```bash
composer ci:check            # npm lint:check + format:check + types:check, then composer test (Pint + Pest)
vendor/bin/phpstan analyse   # Larastan — not in ci:check, run it for backend changes
```

For anything touching rendered UI, rebuild the frontend (`npm run build`) and then run the browser suite
(`php artisan test tests/Browser`) — it serves the built bundle, so an unbuilt change is tested as stale assets. The
suite needs Playwright's browsers installed once (`npx playwright install`); the worktree's own Herd site from
`bin/create-worktree.sh` is what it drives. Never report green without running the gates that match your change.

## List

```bash
git worktree list
git worktree list --porcelain
```

Use porcelain output when deciding what belongs to another agent.

## Remove

Only remove a worktree that belongs to your task and has no needed changes.

```bash
bin/delete-worktree.sh <slug>
```

It refuses to run while the worktree has uncommitted changes, unlinks and unsecures the Herd site, removes the
worktree, and deletes its branch with `git branch -d` — so git itself blocks the delete if the branch was never
merged.

`bin/delete-worktree.sh <slug> --force` skips both guards: it discards uncommitted changes and deletes the branch
with `-D`. Only reach for it when the user explicitly says that work is disposable.

## Multi-Agent Safety

- Prefer separate sibling worktrees over sharing one dirty checkout.
- Never assume a branch, worktree, or untracked file is disposable.
- Commit only your logical change set.
- If two agents touch the same files, stop and coordinate instead of overwriting.
- When worktrees pile up, surface the list and recommend which to close — don't just add more.
