# AGENTS.md

This repo is a **strangler-fig migration demo**: a Cursor SDK orchestrator (TypeScript,
`orchestrator/`) that incrementally modernizes a legacy **osTicket** PHP helpdesk app,
gated by a **sacred parity check**. See `.cursor/rules/repo-context.mdc` and
`.cursor/skills/onboarding/runbook.md` for the authoritative overview and command list.

## Cursor Cloud specific instructions

Environment source of truth is `.cursor/environment.json` → `.cursor/Dockerfile`
(build: Ubuntu 24.04 + Node 22 + `gh` + Docker CE/Compose + `fuse-overlayfs`),
`.cursor/install.sh` (`npm ci`), and `.cursor/start.sh` (start dockerd →
`scripts/ci-docker-bootstrap.sh`). Because the repo pins a Dockerfile, saving a
cloud snapshot is a no-op — make environment changes in the Dockerfile, not a snapshot.

Services (all standard commands live in `.cursor/skills/onboarding/runbook.md`):

| Service | What / how | Notes |
|---------|-----------|-------|
| `db` (MySQL 8) + `web` (PHP 8.4/Apache) | `docker compose` via `scripts/ci-docker-bootstrap.sh`; web on `:8080`, db on `:3306` | Started by `.cursor/start.sh`; bootstrap is idempotent |
| Orchestrator (TypeScript) | run with `npx tsx orchestrator/<entry>.ts` — **no build step** | `tsx` strips types; do not rely on `tsc` |

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
  these are injected as cloud secrets. The parity check itself needs no external APIs.
- If `docker` is missing at runtime, the VM was **not** built from `.cursor/Dockerfile`
  (a snapshot/override). `.cursor/start.sh` prints diagnostics; the fix is to rebuild
  from the repo Dockerfile rather than installing Docker ad hoc.
