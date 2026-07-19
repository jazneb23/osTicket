# Runbook

Confirm commands and env names against the tree. Never print secret values.

## Hands-off production path (preferred)

**Local Docker + local listener.** Parity runs on your machine; nested SDK stages
(cartographer, fixture-generator, pr-agent) still use Cursor cloud agents.

Full setup: [`.github/LOCAL_DEMO_SETUP.md`](../../../.github/LOCAL_DEMO_SETUP.md).

Flow:

1. `bash scripts/local-demo-start.sh` (once per session)
2. `npx tsx orchestrator/listener.ts` (leave running)
3. Move **one** `MOD-*` ticket to **Ready** in Linear
4. Listener claims → **In Progress** → `pipeline.ts`
5. On parity pass: publish → cloud pr-agent → PR → Slack → **In Review**
6. On parity/PR failure or repeated stage crash: **Blocked** → human fixes → move back to **Ready**

**Disable Cursor Automation** for this repo — cloud Docker-in-Docker (`start.sh`)
is not used for the demo and has been unreliable.

Requirements on your laptop: Node 22+, Docker Desktop, `.env` with API keys
(names only in docs — never commit values).

### Cloud environment files (nested SDK agents only)

| Path | Role |
|------|------|
| `.cursor/environment.json` | Cloud agent VM bootstrap (not the demo parity path) |
| `.cursor/Dockerfile` | Node 22 image for nested `withCloudAgent` stages |
| `.cursor/install.sh` | `npm ci` |
| `.cursor/Dockerfile` | Nested cloud image — Node/gh only; **no Docker CE** |
| `.cursor/start.sh` | Legacy DinD bootstrap — **retired** for demo; use `start-cloud-agent.sh` |
| `.cursor/start-cloud-agent.sh` | Cloud agent wake (echo only; no DinD) |

Install `PIPELINE_PAUSE_FOR_REVIEW=1` only if you want the old demo pause
log lines; hands-off default proceeds straight to the PR agent after a pass.

## Local requirements

- Node.js 22+; `npm ci` / `npm install`
- **Docker Desktop** running (parity harness via Compose)
- Port `:8080` is **optional product UI** — not required for the pipeline
- PHP 8.4 + `mysqli` only if you run harness outside Compose (CI installs it)

## Environment variables

Document **names only**. Typical keys in `.env` (gitignored) — mirror the same
names into Cloud Agent secrets:

| Name | Used for |
|------|----------|
| `CURSOR_API_KEY` | Cursor SDK agents |
| `GITHUB_REPO_URL` | Cloud agent repo URL |
| `GITHUB_DEMO_BRANCH` | Optional fallback if `git rev-parse` fails; cloud `startingRef` normally comes from the current checkout |
| `LINEAR_API_KEY` | Ticket status / comments |
| `SLACK_WEBHOOK_URL` | PR-opened notification (optional; warns and skips if unset) |
| `LEGACY_APP_URL` | Legacy app URL for local demo context |
| `PIPELINE_PAUSE_FOR_REVIEW` | Set to `1` for demo pause log only (default hands-off skips) |

Load via `dotenv/config` in pipeline/listener entrypoints.

## Local Docker bootstrap

```bash
bash scripts/local-demo-start.sh
# or: docker compose up -d && bash scripts/ci-docker-bootstrap.sh
# db: MySQL 8 on 3306 (root/osticket, db osticket)
# web: PHP 8.4 Apache on 8080, repo mounted at /var/www/html
```

`scripts/ci-docker-bootstrap.sh` waits for MySQL with an authenticated
`SELECT 1` check, imports schema if needed, and creates `include/ost-config.php`
when missing.

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
npx tsx orchestrator/pipeline.ts MOD-26 --criteria "Extract SLA::priorityEscalation into a service"
```

Resume from a later stage (reuse earlier artifacts):

```bash
npx tsx orchestrator/pipeline.ts MOD-26 --criteria "..." --from-stage 3
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

## CI

Workflow: `.github/workflows/parity-check.yml`

- Triggers: PRs to `demo/sla-strangler` or `develop`; `workflow_dispatch`
- Steps: checkout → Node 22 → PHP 8.4 + mysqli → `npm ci` →
  `scripts/ci-docker-bootstrap.sh` → `npx tsx orchestrator/ci-parity-check.ts`

Parity failure fails the job. Do not weaken the check.

## Where to change what

| Goal | Look here |
|------|-----------|
| Pipeline order / gate | `orchestrator/pipeline.ts` |
| Stage behavior | `orchestrator/agents/<stage>.ts` |
| Manifest schema | `orchestrator/lib/types.ts` |
| Manifest I/O helpers | `orchestrator/lib/manifest.ts` |
| SDK agent wiring | `orchestrator/lib/sdk.ts` |
| Harness execution | `orchestrator/lib/harness.ts`, `legacy/harness/*.php` |
| Publish before cloud PR | `orchestrator/lib/gitPublish.ts` |
| Fixtures | `orchestrator/fixtures/MOD-*/` |
| Extracted services | `include/Services/` |
| Facade patches | Paths in manifest `facadeFile` (often `include/class.sla.php`) |
| Linear / Slack | `orchestrator/lib/linear.ts`, `slack.ts` |
| Cloud VM env | `.cursor/environment.json`, `.cursor/Dockerfile`, `.cursor/start.sh` |
| Process rules | `.cursor/rules/*.mdc` |

## Suggested first week for a new engineer

1. Read `.cursor/rules/repo-context.mdc` and this skill's references.
2. Skim `orchestrator/pipeline.ts` and one agent (e.g. `verifier.ts`).
3. Open one manifest + its fixture dir + harness script; run
   `capture-and-verify` for that ticket (local Docker optional).
4. Trace one extraction: service in `include/Services/` ↔ facade method.
5. Run `ci-parity-check.ts` locally with Docker up, or rely on CI.
6. Only then change orchestrator or add a new seam — manifest-first.

## Package notes

- `package.json` name: `osticket-strangler-demo`
- Dependencies: `@cursor/sdk`, `dotenv`, `tsx`, `typescript`, MCP SDK
- No meaningful `npm test` script yet — parity is the real gate
