# Runbook

Confirm commands and env names against the tree. Never print secret values.

## Running the stock osTicket app

**In this repo, you almost never install osTicket by hand** — Docker Compose
already boots PHP 8.4 + MySQL 8 and bootstraps `include/ost-config.php` for
you. Use that path unless you specifically need a from-scratch
install/upgrade flow.

Requirements (from `README.md`): Apache or IIS, PHP 8.2–8.4 (8.4 recommended),
`mysqli`, MySQL ≥ 5.5. Recommended extensions: ctype, fileinfo, gd, gettext,
iconv, imap, intl, json, mbstring, Zend OPcache, phar, xml, xml-dom, zip, APCu.

Canonical manual deploy (from `README.md`), for reference:

```bash
git clone https://github.com/osTicket/osTicket
cd osTicket
php manage.php deploy --setup /var/www/htdocs/osticket/
# then visit the deployed URL and run the setup/ installer wizard
```

Note: **the Compose bootstrap does not run that installer wizard.** It only
imports the core schema and seeds `ost_schedule` rows for the parity harness
— there's no seeded staff/admin, department, or help topic. So
`http://localhost:8080/` shows "Support Ticket System Offline" and staff
login has no accounts. That's expected; `:8080` is the host-app UI, not
the pipeline under test. Don't "fix" this by running the installer unless
a ticket specifically asks for it.

Upgrades: `UPGRADING.txt` + `include/upgrader/streams/core/` patches, driven
by `include/class.upgrader.php` (`osTicket::isUpgradePending()` compares DB
`schema_signature` to `include/upgrader/streams/core.sig`).

## Hands-off path (preferred for the migration pipeline)

**Local Docker + local listener.** Docker is the runtime for the app and
every harness / parity / CI run. Nested SDK stages (cartographer,
fixture-generator) still use Cursor cloud agents — they must not start
Docker. The PR step runs locally via `gh`.

Full setup: [`.github/LOCAL_DEMO_SETUP.md`](../../../.github/LOCAL_DEMO_SETUP.md).

Flow:

1. `bash scripts/local-demo-start.sh` (once per session — required)
2. `npx tsx orchestrator/listener.ts` (leave running)
3. Move **one** `MOD-*` ticket to **Ready** in Linear
4. Listener claims → **In Progress** → `pipeline.ts`
5. On parity pass: publish → local `gh` PR → Slack → **In Review**. GitHub
   then runs parity CI, Aikido PR Checks, and Kody in parallel.
6. On parity/PR failure or repeated stage crash: **Blocked** → human fixes → move back to **Ready**

**Disable Cursor Automation** for this repo — cloud Docker-in-Docker
(`start.sh`) is not used and has been unreliable.

Requirements on your laptop: Node 22+, Docker Desktop, `.env` with API keys
(names only in docs — never commit values).

### Cloud environment files (nested SDK agents only)

| Path | Role |
|------|------|
| `.cursor/environment.json` | Cloud agent VM bootstrap (not the local Docker parity path) |
| `.cursor/Dockerfile` | Node 22 image for nested `withCloudAgent` stages — Node/gh only, **no Docker CE** |
| `.cursor/install.sh` | `npm ci` |
| `.cursor/start.sh` | Legacy DinD bootstrap — **retired**; use `start-cloud-agent.sh` |
| `.cursor/start-cloud-agent.sh` | Cloud agent wake (echo only; no DinD) |

`PIPELINE_PAUSE_FOR_REVIEW=1` only adds pause log lines;
hands-off default proceeds straight to the PR agent after a pass.

## Local requirements

- Node.js 22+; `npm ci` / `npm install`
- **Docker Desktop** running — required for the pipeline (app + harness + parity)
- Port `:8080` is the host-app UI on the Compose `web` service
- PHP 8.4 + `mysqli` only if you run the harness outside Compose (CI installs it)

## Environment variables

Document **names only**. Typical keys in `.env` (gitignored) — mirror the
same names into Cloud Agent secrets:

| Name | Used for |
|------|----------|
| `CURSOR_API_KEY` | Cursor SDK agents |
| `GITHUB_REPO_URL` | Cloud agent repo URL |
| `GITHUB_DEMO_BRANCH` | Optional fallback if `git rev-parse` fails |
| `LINEAR_API_KEY` | Ticket status / comments |
| `SLACK_WEBHOOK_URL` | PR-opened notification (optional; warns and skips if unset) |
| `LEGACY_APP_URL` | Legacy app URL for local context |
| `PIPELINE_PAUSE_FOR_REVIEW` | Set to `1` for pause log only |

Load via `dotenv/config` in pipeline/listener entrypoints.

## Local Docker bootstrap

```bash
bash scripts/local-demo-start.sh
# or: docker compose up -d && bash scripts/ci-docker-bootstrap.sh
# db: MySQL 8 on 3306 (root/osticket, db osticket)
# web: PHP 8.4 Apache on 8080, repo mounted at /var/www/html
```

`scripts/ci-docker-bootstrap.sh` waits for MySQL with an authenticated
`SELECT 1` check, imports the core schema if needed, and creates
`include/ost-config.php` when missing.

`include/ost-config.php` is gitignored — local/CI/cloud generate it; do not
commit secrets.

## Day-to-day commands

### First-time local setup (optional)

```bash
npm ci
# Create .env with required keys (see table above; file is gitignored)
docker compose up -d
bash scripts/ci-docker-bootstrap.sh
```

### Run the full migration pipeline for a ticket (local debug)

```bash
npx tsx orchestrator/pipeline.ts MOD-27 --criteria "Extract Ticket overdue handling into a service"
```

Resume from a later stage (reuse earlier artifacts):

```bash
npx tsx orchestrator/pipeline.ts MOD-27 --criteria "..." --from-stage 3
```

### Production: local Linear listener

```bash
bash scripts/local-demo-start.sh   # once
npx tsx orchestrator/listener.ts   # leave running; polls Ready every 5s
```

Do **not** run the listener while Cursor Automation is Active — double starts.

### Re-capture baselines and verify one seam

```bash
npx tsx orchestrator/capture-and-verify.ts MOD-25
```

### Run what CI runs

```bash
npx tsx orchestrator/ci-parity-check.ts
```

### Direct harness smoke test (proves web+db+PHP bootstrap)

```bash
docker compose exec -T web php legacy/harness/sla_capture.php \
  '{"start":"2024-01-15T10:00:00-05:00","hours":2,"schedule_id":1}'
# → output should be 2024-01-15T12:00:00
```

## CI

Workflow: `.github/workflows/parity-check.yml`

- Triggers: PRs to `demo/sla-strangler` or `develop`; `workflow_dispatch`
- Steps:
  1. Checkout + Node 22 + `npm ci`
  2. **Evaluate** parity scope: `npx tsx orchestrator/ci-parity-check.ts --evaluate`
  3. If output is `run`: PHP 8.4 + mysqli → `scripts/ci-docker-bootstrap.sh`
     (Docker) → `npx tsx orchestrator/ci-parity-check.ts`
  4. If not `run`: skip (no seam changes and/or no cartographer manifest in
     checkout — runtime `.state/` or `orchestrator/manifests/`)

Parity failure fails the job. Do not weaken the check. There is no separate
PHPUnit/host-app CI job — `setup/test/run-tests.php` static checks are not
wired into `.github/workflows/` in this repo.

After the PR opens, **Aikido PR Checks** (GitHub App, not Actions) and
**Kody** (comments only; auto-approve off) run in parallel with this workflow.

## Where to change what

| Goal | Look here |
|------|-----------|
| Host app request handling | `main.inc.php`, `bootstrap.php`, `client.inc.php`, `scp/staff.inc.php` |
| Host app domain logic | `include/class.*.php` (see `host-app.md`) |
| Host app DB schema | `setup/inc/streams/core/install-mysql.sql`, `include/upgrader/streams/core/` |
| Host app templates | `include/staff/`, `include/client/` |
| Pipeline order / gate | `orchestrator/pipeline.ts` |
| Stage behavior | `orchestrator/agents/<stage>.ts` |
| Sentinel / Aikido MCP | `orchestrator/lib/sentinel.ts`, `aikidoMcpScan.ts` |
| Manifest schema | `orchestrator/lib/types.ts` |
| Manifest I/O + pinned tickets | `orchestrator/lib/manifest.ts` |
| SDK agent wiring | `orchestrator/lib/sdk.ts` |
| Harness execution | `orchestrator/lib/harness.ts`, `legacy/harness/*.php` |
| Fixtures | `orchestrator/fixtures/MOD-*/` |
| Extracted services | `include/Services/` |
| Facade patches | Paths in manifest `facadeFile` (e.g. `include/class.sla.php`, `include/class.ticket.php`) |
| Linear / Slack | `orchestrator/lib/linear.ts`, `slack.ts` |
| Parity CI | `.github/workflows/parity-check.yml`, `orchestrator/ci-parity-check.ts` |
| Kody review | `kodus-config.yml`, `.kody/rules/` |
| Cloud VM env | `.cursor/environment.json`, `.cursor/Dockerfile`, `.cursor/start.sh` |
| Process rules | `.cursor/rules/*.mdc` |

## Suggested first week for a new engineer

1. Read `README.md` for what osTicket does, then `.cursor/rules/repo-context.mdc`
   for what this repo adds on top.
2. Boot the stack: `bash scripts/local-demo-start.sh`. Browse `:8080` (expect
   "offline" — that's normal, see above).
3. Skim `bootstrap.php` → `main.inc.php` → one host-app class (e.g.
   `include/class.ticket.php`) to see the request lifecycle end to end.
4. Skim `orchestrator/pipeline.ts` and one agent (e.g. `verifier.ts`).
5. Open one fixture dir + its harness script; run `capture-and-verify` for
   that ticket.
6. Trace one extraction: service in `include/Services/` ↔ facade method it
   patches.
7. Run `ci-parity-check.ts` locally with Docker up, or rely on CI.
8. Only then change host-app code or the orchestrator — for extraction
   seams, stay manifest-first and scoped to what the ticket names.

## Package notes

- `package.json` name: `osticket-strangler-demo`
- Dependencies: `@cursor/sdk`, `dotenv`, `tsx`, `typescript`, MCP SDK
- No meaningful `npm test` script — parity is the real gate for pipeline
  code; there is no automated test suite for the host app in this repo (see
  `host-app.md` Testing section).
- No root `composer.json` — host-app PHP dependencies are vendored in-tree.
