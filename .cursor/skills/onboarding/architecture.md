# Architecture

Spot-check the tree before treating paths, versions, or class lists as fact.

## Dual nature of the tree

This repository is **osTicket (PHP host app) + a demo overlay**. The vast
majority of the tree — root PHP, `scp/`, `api/`, `setup/`, `include/class.*.php`,
`css/`, `js/` — is the stock osTicket application. The strangler-fig demo
lives in a small, clearly separated set of surfaces on top of it.

| Area | Role | Surface |
|------|------|---------|
| Root `*.php` (`index.php`, `tickets.php`, `open.php`, …) | Client portal entry points | **Host app** |
| `scp/` | Staff Control Panel (agents/admins) | **Host app** |
| `api/` | HTTP API + email pipe/cron endpoints | **Host app** |
| `setup/` | Installer + upgrade UI + SQL streams | **Host app** |
| `include/` | Domain classes, ORM, config, staff/client views, plugins, vendored libs | **Host app** (except `include/Services/`) |
| `css/`, `js/`, `images/`, `assets/`, `kb/`, `pages/`, `apps/` | Static/UI/CMS surfaces | **Host app** |
| `orchestrator/` | Pipeline, agents, SDK wrappers, Linear/Slack, fixtures, manifests | **Demo** |
| `include/Services/` | Extracted PHP service classes | **Demo** |
| `legacy/harness/` | Parity capture scripts (CLI tools, not product) | **Demo** |
| `orchestrator/fixtures/` | Golden baselines per ticket (`MOD-*`) | **Demo** |
| `.cursor/rules/`, `.cursor/skills/` | Process gates and agent skills | **Demo tooling** (applies to whole repo where relevant) |
| `.github/workflows/` | Parity CI | **Demo** |
| `kodus-config.yml`, `.kody/rules/` | Kodus (Kody) PR review config + strangler rules; auto-approve off | **Demo** |
| `docker/`, `docker-compose.yml`, `scripts/` | Local Docker for parity + PHP/MySQL | **Demo tooling**, runs the **host app** |
| `include/*/vendor/` (mpdf, laminas-mail), `include/pear/`, `include/fpdf/` | Vendored third-party PHP | **Host app — do not touch** |
| Root `fixtures/` | Empty/misleading legacy dir | Prefer `orchestrator/fixtures/` |

Root `README.md`, `WHATSNEW.md`, `UPGRADING.txt`, `SECURITY.md` are genuine
**upstream osTicket** docs — accurate for the host app, silent on the demo.

## Source of truth (priority order)

1. `.cursor/rules/*.mdc` — process, gates, ownership (demo-scoped, but repo-wide-applied)
2. `bootstrap.php`, `main.inc.php` — host app entry/lifecycle
3. `orchestrator/agents/*.ts`, `orchestrator/pipeline.ts`, `orchestrator/lib/types.ts` — demo stage order + schemas
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

## Demo overlay layout (see `demo-overlay.md` for depth)

```
orchestrator/
├── pipeline.ts          # Full stage runner (CLI / listener entry)
├── listener.ts          # Production: Linear Ready poller (local Docker)
├── capture-and-verify.ts
├── ci-parity-check.ts   # CI: all fixture suites
├── agents/              # Thin stage wrappers (cartographer → … → prAgent)
├── lib/                 # sdk, manifest, harness, gitPublish, linear, slack, terminal
├── fixtures/            # MOD-*/**.json golden cases + parity.json
├── manifests/           # PR-branch copy of seam JSON for CI (not on develop)
└── .state/              # Runtime cache (gitignored) — cartographer writes here
```

Local demo: Docker Desktop + `scripts/local-demo-start.sh` for parity;
`listener.ts` for Linear Ready. Nested SDK agents use Cursor cloud VMs via
`withCloudAgent` — they do not run the parity harness.

## Strangler-fig pattern (in this demo)

1. **Cartograph** the seam (no code changes).
2. **Capture** real legacy behavior into fixtures via a harness.
3. **Extract** core logic into `include/Services/<Name>.php`.
4. **Strangle** the facade: keep the public method signature; delegate to the service.
5. **Sentinel** — inline Aikido scan of the new files (secrets halt; SAST reported).
6. **Verify** harness output still matches fixture `expected` values.
7. Only then open a PR and notify humans.

After the PR opens, GitHub review gates run in parallel (Parity CI, **Aikido
PR Checks GitHub App**, **Kodus / Kody**). Aikido PR Checks is the merge-time
AppSec gate and does **not** use Actions minutes — do not add an Aikido
workflow. Kodus is **not** an orchestrator stage. It comments on the PR;
auto-approve is off — a human is the only approver. Demo PRs still must not
merge into `develop`.

Anti-recursion: the service must never call back into the facade entry point
it replaced.

## Integrations

| System | Role |
|--------|------|
| Cursor SDK (`@cursor/sdk`) | Local/cloud agents for cartography, codegen, PR (demo only) |
| Linear | Demo ticket Ready → In Progress → In Review; failures → Blocked |
| Slack | Demo: notify only after parity pass + PR |
| GitHub Actions | Golden fixture parity on PRs (demo; best-effort if Actions minutes are exhausted) |
| Aikido | Two surfaces: Sentinel MCP in the pipeline (secrets halt; SAST reported), plus **Aikido PR Checks GitHub App** on opened PRs. AppSec does not depend on Actions minutes — do not add an Aikido workflow |
| Kodus (Kody) | OSS AI code review on opened PRs (GitHub App). Comments only — `pullRequestApprovalActive: false`. Does not replace Sentinel or the verifier. Config: `kodus-config.yml`; rules: `.kody/rules/` |
| Docker Compose | MySQL + PHP Apache — runs the **host app** for both manual browsing and the demo's parity harness |
