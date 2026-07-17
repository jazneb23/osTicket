# AGENTS.md

This repo is a **strangler-fig migration demo**: a Cursor SDK orchestrator (TypeScript,
`orchestrator/`) that incrementally modernizes a legacy **osTicket** PHP helpdesk app,
gated by a **sacred parity check**. See `.cursor/rules/repo-context.mdc` and
`.cursor/skills/onboarding/runbook.md` for the authoritative overview and command list.

## Local demo instructions

**Production path:** local Docker + `orchestrator/listener.ts`. See
`.github/LOCAL_DEMO_SETUP.md` and `.cursor/skills/onboarding/runbook.md`.

Parity runs via `docker compose exec` on your machine (`scripts/local-demo-start.sh`).
Nested SDK stages (cartographer, fixture-generator, pr-agent) use Cursor cloud
agents via `@cursor/sdk` — they do not need Docker on the cloud VM.

## Cursor Cloud files (nested agents only)

`.cursor/environment.json`, `.cursor/install.sh`, and `.cursor/start-cloud-agent.sh`
bootstrap nested `withCloudAgent` VMs (no DinD). Legacy `.cursor/start.sh` is
retired for the demo. **Do not** use Cursor Automation to run the full pipeline.

Services (commands in `.cursor/skills/onboarding/runbook.md`):

| Service | What / how | Notes |
|---------|-----------|-------|
| `db` (MySQL 8) + `web` (PHP 8.4/Apache) | Local `docker compose` via `scripts/local-demo-start.sh`; web on `:8080`, db on `:3306` | Required for parity on the demo machine |
| Orchestrator (TypeScript) | `npx tsx orchestrator/<entry>.ts` — **no build step** | `tsx` strips types; do not rely on `tsc` |

Non-obvious gotchas (durable):

- **No `tsc` build.** The orchestrator runs via `tsx`. `npx tsc --noEmit` fails
  (no `@types/node`; legacy `orchestrator/test-stage*.ts` files) and is **not** part
  of the workflow. The real quality gate is the parity check.
- **The demo does not run the osTicket install wizard.** Bootstrap only imports the
  core schema and seeds `ost_schedule` rows for the parity harness — there is no
  seeded staff/admin, department, or help topic. So `http://localhost:8080/` shows
  "Support Ticket System Offline" and staff login has no accounts. This is expected;
  `:8080` is an optional host-app UI, not the product flow. Do **not** "fix" it by
  running the installer unless a ticket asks for it.
- **Parity gate skips on the base branch.** `orchestrator/ci-parity-check.ts` only
  verifies when a `MOD-*` seam manifest exists in `orchestrator/.state/` (gitignored,
  produced at runtime by the cartographer stage). To exercise the gate manually against
  the committed MOD-25 fixtures, create `orchestrator/.state/MOD-25-manifest.json`
  (fields per `orchestrator/lib/types.ts` `SeamManifest`; MOD-25 pins its harness via
  `applyHarnessDefaults`) then run `npx tsx orchestrator/capture-and-verify.ts MOD-25`.
- **Direct harness smoke test** (proves web+db+PHP bootstrap):
  `docker compose exec -T web php legacy/harness/sla_capture.php '{"start":"2024-01-15T10:00:00-05:00","hours":2,"schedule_id":1}'`
  → `output` should be `2024-01-15T12:00:00`.
- The verifier writes a generated `orchestrator/fixtures/MOD-*/parity.json` report
  (untracked) — do not commit it.
- Full pipeline (`orchestrator/pipeline.ts`) needs
  `CURSOR_API_KEY`, `LINEAR_API_KEY`, `GITHUB_*` (and optional `SLACK_WEBHOOK_URL`);
  run locally with Docker Desktop for parity. The parity check itself needs no external APIs beyond Compose.
- If parity fails with "docker compose" errors, run `bash scripts/local-demo-start.sh`
  and confirm Docker Desktop is running — do not use cloud `start.sh` for the demo.
