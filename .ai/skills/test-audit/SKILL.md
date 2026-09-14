---
name: test-audit
description: Use when auditing or cleaning up this app's test suites (Pest Feature/Unit/Browser) — low-value or obsolete tests, duplicated coverage or helpers, tests that belong in a library or package, feature tests faking browser behavior, suite-wide drift — or when asked to "get rid of useless tests" or consolidate test infrastructure.
---

# Test Suite Audit & Cleanup

## Overview

A test suite audit removes tests that assert nothing real, moves helpers to the layer that owns them, and converts
tests that fake their environment to the runner that can exercise them honestly. The quality bar for individual tests
is defined in the Testing rule (`.ai/rules/testing.md`) — this skill is the _process_ for applying it at
suite scale.

Not for writing individual new tests — the Testing rule covers that bar directly.

## When to use

- The suites have accumulated render-only, payload-existence, `assertDontSee`, or copy-paste tests.
- Helpers or datasets are duplicated across test files instead of living in `tests/` shared infrastructure.
- Feature tests assert serialized Lattice payload fragments as a proxy for browser behavior.
- It is unclear which suites CI actually runs, or local-only test config has drifted.

## Workflow

1. **Isolate.** Create a worktree from the latest main (use the `worktrees` skill). Never audit on a shared dirty
   checkout.

2. **Map the infrastructure before reading any test.**
    - Which suites run in CI? (`.github/workflows/` is the truth; note that `composer test` currently runs `tests/Browser` too.)
    - Inventory shared infrastructure: `tests/Pest.php`, `Tests\TestCase`, the `InteractsWithLatticeComponents`
      helpers, model factories and their states, shared datasets.
    - Map package ownership before judging coverage. Generic behavior from a Lattice package or another
      vendor package belongs in that package's test suite. App-specific configuration and integration remain app-owned.
    - Grep for inline duplication clusters: hand-rolled realm/user setup that a factory state owns, repeated
      OAuth client bootstrapping, copy-pasted context arrays for `submitForm`/`callAction`. 2+ definitions of the
      same concept is a consolidation finding.

3. **Audit the tests.** Read every test file — grep alone misses most findings. At scale, fan out one read-only
   subagent per domain (`tests/Feature/Auth`, `Admin`, `Shared`, `Realms`, …); require
   each finding as `file:line` + test name + one-line justification + a verdict:
    - **DELETE** — fails the Testing guideline's bar (render-only, payload-existence, absence-only, tautology,
      obsolete pin, duplicate).
    - **TRIM** — the test is sound but carries dead assertions or duplicates a sibling; also collapse N near-identical
      tests into one dataset.
    - **MOVE** — generic behavior belongs in a Lattice or other vendor package's suite, or the
      helper belongs in shared infrastructure. Name the owning package and its exact surviving or required test.
    - **CONVERT** — a feature test asserting UI behavior through payload fragments becomes a Pest browser test.

    Also collect **exemplars** — the suite's best tests define the house style the survivors should match.

4. **Verify before deleting — the iron rule.** A test dies only when its behavior is worthless _or_ has exactly one
   surviving owner, named in the verdict. If package-owned behavior lacks upstream coverage, add the test to the
   owning package before deleting the app test; never create or retain an app test as a substitute. A CONVERT deletes
   the original only after its replacement passes. Never batch-delete on category alone; the audit lists are
   hypotheses until checked against the surviving suite.

5. **Apply in ordered commits.**
    1. _Infrastructure consolidation first_ — otherwise helpers centralize into drifting copies again.
    2. _Helper moves_ — create the owned helper/factory state, update every caller, delete the copies.
    3. _Deletions/trims_ — disjoint domains parallelize safely across subagents.
    4. _Conversions_ — the slowest, most careful work.

6. **Gate every stage.** `composer ci:check` and `vendor/bin/phpstan analyse` after each commit-sized step. When
   rendered behavior moved, `npm run build` then `php artisan test tests/Browser` — browser tests serve the last
   built bundle and need the app reachable at `lock.test`.

## Verdict cheat-sheet

| Smell                                                                                     | Verdict                                                   |
| ----------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| Page test that only asserts static text or a component's presence in the payload          | DELETE                                                    |
| Assertion on a class string mirrored from a Lattice definition                            | DELETE                                                    |
| Mock/factory configured, then asserted                                                    | DELETE                                                    |
| Same behavior asserted in N consumers (e.g. auth denial per page when middleware owns it) | DELETE N−1, name the owner                                |
| Sound test + dead payload/absence assertions                                              | TRIM                                                      |
| N clones differing by one value                                                           | TRIM to a dataset                                         |
| Test re-proving generic behavior owned by a library or package                            | MOVE; add or identify upstream coverage, then DELETE here |
| Feature test walking serialized schema to "click" something                               | CONVERT to browser                                        |

## Traps

- **Agents recreate deleted patterns from "sibling convention".** After a purge, an unguided agent asked to test a
  page will rebuild the exact payload-existence file you deleted. The Testing rule is the counterweight — point
  implementation subagents at it explicitly.
- **App tests become a substitute package suite.** Do not add or keep an app test because upstream coverage is
  missing. Put generic behavior in the owning library's suite; keep only app-specific wiring, configuration, and
  observable integration behavior here.
- **A test that only passes because the environment is fake** (asserting payload fragments that no browser ever
  renders, seeding state the real flow can't reach) is not coverage — CONVERT or DELETE even though it is green.
- **Browser failure artifacts** (screenshots, attachments) appear on red runs — keep them gitignored and out of
  commits (`git add -A` after a red browser run is how they sneak in).
- **Stale `public/hot`**: a leftover Vite hot file makes every browser test hang on dev-server assets. Delete it
  before browser runs when no dev server is running.
