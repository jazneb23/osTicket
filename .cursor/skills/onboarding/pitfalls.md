# Sacred rules, conventions, and pitfalls

## Sacred: demo parity gate

Applies to work on the strangler-fig migration surfaces
(`orchestrator/`, `include/Services/`, `legacy/harness/`, facade files named
by a seam manifest) — **not** a rule for ordinary host-app work.

- Never skip, weaken, or bypass the verifier.
- Never invent `expected` values or edit fixtures just to make a run pass.
- Do not open a PR or move Linear to **In Review** unless `gatePassed === true`.
- On failure: halt; move ticket to Blocked; no success Slack.

Harnesses bootstrap through `main.inc.php`. Never load class files in
isolation (breaks `INCLUDE_DIR` / DB context). Prefer an existing harness
when it already covers the seam.

## Sacred: demo scope

Only touch files the **current ticket's seam manifest** names:
`extractionTarget`, `facadeFile`, and the harness/fixture paths the stage owns.

Do **not** edit:

- `include/*/vendor/`, mpdf/laminas vendor trees (applies to host-app work
  in general, not just the demo)
- Unrelated osTicket PHP/UI unless the manifest explicitly names it

## Sacred: strangler quality

- Preserve facade method **signatures** and caller-visible contracts.
- Preserve facade-level orchestration (globals, schedule resolution, caching).
- Move only core logic into the extracted service.
- **Anti-recursion**: the extracted service must not call back into the
  facade entry point it replaces. Use lifted logic or forward delegation to
  deeper unrelated code only.

## Host-app conventions (ordinary, non-demo PHP work)

- 4-space indent (`expandtab sw=4 ts=4 sts=4`), informal historical PHP
  style — not PSR-12 enforced, no `.editorconfig`/PHPCS at repo root.
- No app-wide Composer autoload; classes are `require`d explicitly. Don't
  invent a Composer-based dependency for the core app without a strong reason.
- New user-facing strings should be wrapped for i18n per `setup/doc/i18n.md`.
- There is no PHPUnit/behavioral test suite for the host app
  (`setup/test/` is static/hygiene checks only) — don't assume `npm test` or
  a PHP test runner will catch regressions; verify manually or via the demo
  harness pattern if the surface overlaps a seam.
- The demo's Docker bootstrap intentionally skips the install wizard (no
  seeded staff/admin/department/help topic) — don't "fix" `:8080` showing
  "offline" by running the installer unless asked.
- **ORM and legacy string-SQL coexist in the same class** (e.g. `Sla::delete()`
  in `include/class.sla.php` still hand-builds SQL with `db_input()`/`db_query()`
  next to methods that use `::objects()->filter()`). Check each method's data
  layer before assuming a class is "pure ORM."
- **Timezone math**: prefer `Misc::dbtime()` (`include/class.misc.php`) over
  raw `time()` when comparing against DB datetime columns — DB and PHP
  timezones can differ (`$cfg->getDbTimezone()`); using `time()` directly is
  a common source of off-by-some-hours bugs.
- Almost every method on `Ticket`/`Staff`/etc. reaches for `global $thisstaff, $cfg;`
  rather than accepting them as parameters — expect implicit globals, not
  dependency injection, throughout the host app.

## Orchestrator (demo) conventions

- Keep stage agents thin — prompts + I/O; reuse `lib/sdk`, `lib/manifest`, etc.
- Persist runtime state under `orchestrator/.state/` only (gitignored).
- Do not hardcode ticket-specific paths in new code except where
  `lib/manifest.ts` already deliberately special-cases a ticket (e.g. MOD-25's
  harness path, MOD-27's `PINNED_MANIFESTS` regression demo). New tickets are
  manifest-driven.
- Linear/Slack: use existing helpers (`buildInReviewComment`, `notifyPrOpened`,
  `updateTicketStatus`, …). Do not invent parallel payload formats.
- Notify / status transitions **only after** a genuine parity pass.

## Cursor rules map

| Rule file | Applies to | Focus |
|-----------|------------|-------|
| `repo-context.mdc` | Always | Demo vs host, parity sacred, leave unrelated trees alone |
| `orchestrator-typescript.mdc` | `orchestrator/**/*.ts` | Thin agents, `.state/`, no new hardcoding beyond pinned exceptions |
| `harness-fixtures.mdc` | harness/fixtures/related agents | `main.inc.php`, real expecteds, reuse harnesses |
| `php-extraction-strangler.mdc` | Services + named facades | Smallest patch, anti-recursion, manifest-scoped files |
| `linear-slack-pipeline.mdc` | linear/slack/pipeline | Gate-first notifications |

## Common pitfalls

| Pitfall | Reality |
|---------|---------|
| Assuming this repo is "just the demo" | Most of the tree is the stock osTicket app; the demo is a thin, clearly-scoped overlay |
| Reading root `README.md` for demo architecture | It's accurate upstream osTicket context, but says nothing about the pipeline/gate |
| Using root `fixtures/` | Empty/misleading; use `orchestrator/fixtures/` |
| Treating all of `include/` as fair game | Most is host app; prefer `Services/` + manifest paths for demo work |
| Assuming a PHPUnit suite exists | It doesn't; `setup/test/` is static checks only |
| Assuming `orchestrator/manifests/` exists | It doesn't in the current tree — check `.state/` and `lib/manifest.ts` `PINNED_MANIFESTS` instead |
| Assuming MOD-26 exists | Ticket ids get reset/replayed for the demo; always check `orchestrator/fixtures/` for what's actually active |
| Guessing fixture expecteds | Must come from baseline capture against legacy behavior |
| "Fixing" parity by changing fixtures | Forbidden — fix extraction/strangler or harness instead |
| "Fixing" MOD-27's `checkOverdue()` bug casually | It's an intentional pinned regression for the Blocked-flow demo |
| Opening PR on failed gate | Forbidden |
| Calling facade from extracted service | Infinite recursion after strangler patch |
| Isolated PHP requires of one class (in a harness) | Harness must use full bootstrap |
| Parallel Linear/Slack message formats | Reuse existing builders |
| "Fixing" `:8080` offline banner by running the installer | Intentional — demo doesn't seed staff/admin |

## Glossary

| Term | Meaning |
|------|---------|
| Facade | Existing public API on a host-app class (e.g. `Ticket::markOverdue`) kept stable for callers |
| Seam | Boundary where a legacy entry point will delegate to extracted code |
| Extraction target | New class under `include/Services/` |
| Harness | CLI PHP script that exercises a seam and prints JSON |
| Golden fixture | Checked-in input + captured `expected` output |
| Parity gate | Verifier requiring all fixtures to match before PR |
| Strangler-fig | Incrementally replace legacy behavior behind a stable facade |
| ORM (this repo) | Custom Django-style `VerySimpleModel`/`QuerySet` in `include/class.orm.php` — not ADODB/Doctrine |
