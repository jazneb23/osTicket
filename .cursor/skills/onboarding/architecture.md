# Architecture

Spot-check the tree before treating paths, versions, or class lists as fact.

## Dual nature of the tree

This repository is **osTicket (PHP host app) + a strangler-fig migration
pipeline**. The vast majority of the tree — root PHP, `scp/`, `api/`,
`setup/`, `include/class.*.php`, `css/`, `js/` — is the stock osTicket
application. The extraction work lives in a small, clearly separated set of
surfaces on top of it.

| Area | Role | Surface |
|------|------|---------|
| Root `*.php` (`index.php`, `tickets.php`, `open.php`, …) | Client portal entry points | **Host app** |
| `scp/` | Staff Control Panel (agents/admins) | **Host app** |
| `api/` | HTTP API + email pipe/cron endpoints | **Host app** |
| `setup/` | Installer + upgrade UI + SQL streams | **Host app** |
| `include/` | Domain classes, ORM, config, staff/client views, plugins, vendored libs | **Host app** (except `include/Services/`) |
| `css/`, `js/`, `images/`, `assets/`, `kb/`, `pages/`, `apps/` | Static/UI/CMS surfaces | **Host app** |
| `orchestrator/` | Pipeline, agents, SDK wrappers, Linear/Slack, fixtures, manifests | **Migration pipeline** |
| `include/Services/` | Extracted PHP service classes | **Migration pipeline** |
| `legacy/harness/` | Parity capture scripts (CLI tools, not product) | **Migration pipeline** |
| `orchestrator/fixtures/` | Golden baselines per ticket (`MOD-*`) | **Migration pipeline** |
| `.cursor/rules/`, `.cursor/skills/` | Process gates and agent skills | **Tooling** (applies to whole repo where relevant) |
| `.github/workflows/` | Parity CI | **CI** |
| `kodus-config.yml`, `.kody/rules/` | Kodus (Kody) PR review config + strangler rules; auto-approve off | **Code review** |
| `docker/`, `docker-compose.yml`, `scripts/` | Docker runtime for the host app + parity harness | **Runtime** |
| `include/*/vendor/` (mpdf, laminas-mail), `include/pear/`, `include/fpdf/` | Vendored third-party PHP | **Host app — do not touch** |
| Root `fixtures/` | Empty/misleading legacy dir | Prefer `orchestrator/fixtures/` |

Root `README.md`, `WHATSNEW.md`, `UPGRADING.txt`, `SECURITY.md` are genuine
**upstream osTicket** docs — accurate for the host app, silent on the
orchestrator.

## Source of truth (priority order)

1. `.cursor/rules/*.mdc` — process, gates, ownership
2. `bootstrap.php`, `main.inc.php` — host app entry/lifecycle
3. `orchestrator/agents/*.ts`, `orchestrator/pipeline.ts`, `orchestrator/lib/types.ts` — stage order + schemas
4. Real class files, manifests, fixtures, harnesses under `include/`, `orchestrator/`, `legacy/`

## Host app request lifecycle (high level — see `host-app.md` for depth)

```
Browser
  ├─ /*.php          → client.inc.php  → main.inc.php → bootstrap.php → ORM/mysqli → include/client/
  ├─ /scp/*.php      → scp/staff.inc.php (+ admin.inc.php) → main.inc.php → …       → include/staff/
  ├─ /api/http.php   → api/api.inc.php → main.inc.php → class.dispatcher.php → controllers
  └─ /setup/*        → installer / upgrader (install-mysql.sql + upgrader/streams/core/)
```

`main.inc.php` is the brain of every web request: it runs `bootstrap.php`'s
`Bootstrap::{init,loadConfig,defineTables,loadCode,connect,i18n_prep}`, then
`osTicket::start()` (config, session, CSRF, plugin bootstrap, search), then
hands off to the client/staff/API layer for auth and page logic.

There is no app-wide Composer autoload; most classes are `require`d
explicitly (see `Bootstrap::loadCode()`). Namespaced/plugin code uses a
Symfony-derived `include/UniversalClassLoader.php`; vendored libs
(laminas-mail, mPDF) bring their own Composer autoloaders.

## SDLC

```
Linear Ready
  → listener claims ticket (In Progress) — Docker must be up
  → 1  Cartographer (cloud) — seam manifest
  → 2a Harness builder (local)
  → 2b Fixture generator (cloud)
  → 2c Baseline capture (Docker) — real expecteds
  → 3  Extractor (local) — include/Services/
  → 4  Strangler (local) — facade patch
  → 4b Aikido Sentinel (secrets halt, no PR; SAST reported, continue)
  → 5  Verifier (Docker) — parity gate
  → 6  PR agent (local gh) — only if gatePassed
  → GitHub checks in parallel:
       Golden fixture parity (Actions + Docker)
       Aikido PR Checks (GitHub App, not Actions)
       Kody code review (comments; auto-approve off)
  → Linear In Review + Slack
Secrets or parity fail → Linear Blocked (listener picks the next Ready ticket)
```

A human is the only GitHub approver. Kody is **not** a `pipeline.ts` stage.

## Orchestrator layout (see `demo-overlay.md` for depth)

```
orchestrator/
├── pipeline.ts          # Full stage runner (CLI / listener entry)
├── listener.ts          # Linear Ready poller (requires local Docker)
├── capture-and-verify.ts
├── ci-parity-check.ts   # CI: evaluate scope, then fixture suites
├── agents/              # Thin stage wrappers (cartographer → … → prAgent)
├── lib/                 # sdk, manifest, harness, gitPublish, linear, slack, sentinel, dockerPreflight
├── fixtures/            # MOD-*/**.json golden cases + parity.json
├── manifests/           # PR-branch copy of seam JSON for CI (not on develop)
└── .state/              # Runtime cache (gitignored) — cartographer writes here
```

**Docker** is the runtime: Compose `db` (MySQL 8 on `:3306`) + `web`
(PHP 8.4/Apache on `:8080`, repo mounted at `/var/www/html`). Start it with
`scripts/local-demo-start.sh`. Listener and pipeline call
`assertLocalDockerReady()` before running. Harness, baseline capture,
verifier, and CI parity all `docker compose exec` against this stack.
Nested SDK agents (cartographer, fixture-generator) use Cursor cloud VMs via
`withCloudAgent` — they must not start Docker.

## Strangler-fig pattern

1. **Cartograph** the seam (no code changes).
2. **Capture** real legacy behavior into fixtures via a harness.
3. **Extract** core logic into `include/Services/<Name>.php`.
4. **Strangle** the facade: keep the public method signature; delegate to the service.
5. **Sentinel** — inline Aikido scan of the new files (secrets halt; SAST reported).
6. **Verify** harness output still matches fixture `expected` values.
7. Only then open a PR. GitHub review gates run in parallel (parity CI,
   Aikido PR Checks, Kody).

Anti-recursion: the service must never call back into the facade entry point
it replaced.

## Integrations

| System | Role |
|--------|------|
| Cursor SDK (`@cursor/sdk`) | Local agents (extract, strangle, harness) and cloud agents (cartograph, fixtures) |
| Linear | Ticket Ready → In Progress → In Review; failures → Blocked |
| Slack | Notify only after parity pass + PR |
| Docker Compose | Runtime for the host app and the parity harness (MySQL 8 + PHP 8.4/Apache) |
| GitHub Actions | Golden fixture parity on PRs: evaluate scope, then Docker bootstrap + verifier. Needs a seam manifest in checkout |
| Aikido | Two surfaces: **Sentinel** (Aikido MCP in the pipeline — secrets halt; SAST reported) and **Aikido PR Checks** (GitHub App on opened PRs — merge-time AppSec, not Actions). Do not add an Aikido workflow |
| Kodus (Kody) | AI code review on opened PRs. Comments only — `pullRequestApprovalActive: false`. Does not replace Sentinel or the verifier. Config: `kodus-config.yml`; rules: `.kody/rules/` |
| `gh` CLI | PR agent opens the PR locally on the listener machine (cloud agents lack GitHub PR permissions) |
