# AGENTS.md

## Cursor Cloud specific instructions

This repo is a strangler-fig migration demo: a TypeScript/Node **orchestrator** (Cursor SDK, in `orchestrator/`) wrapped around a legacy PHP **osTicket** app (repo root), gated by a golden-fixture **parity check**. The orchestrator is the product being developed; osTicket is the legacy system-under-test.

### Environment / how it boots
- The cloud environment is defined by `.cursor/environment.json` → `.cursor/Dockerfile` (Docker-in-Docker + Node 22 + `gh`), `.cursor/install.sh` (`npm ci`), and `.cursor/start.sh`. Treat these as the source of truth; the Docker daemon + DinD tuning (fuse-overlayfs, iptables-legacy) come from the Dockerfile.
- To (re)start services on a fresh session, run `bash .cursor/start.sh` (idempotent). It starts the Docker daemon and calls `scripts/ci-docker-bootstrap.sh`, which brings up the Compose stack and imports schema/seeds/config.

### Running the legacy osTicket app (Product A)
- Served at `http://localhost:8080` (Apache/PHP 8.4); MySQL 8.0 on `:3306`. Both run via `docker compose` (`web`, `db`).
- Non-obvious: the bootstrap only imports the osTicket schema + minimal config + SLA schedules needed by the parity harness. It does **not** run the osTicket install wizard, so the client portal (`/open.php`) shows "Support Ticket System Offline" and there are no seeded staff, departments, or help topics. This is expected — do not "fix" it unless a ticket asks for full osTicket installation. The staff login UI (`/scp/login.php`) renders but has no seeded accounts.
- DB creds (local only): db host `db`, user `root`, password `osticket`, database `osticket`, table prefix `ost_`. Generated config lives at `include/ost-config.php` (gitignored).

### Parity gate (Product B core) — the sacred check
- Run: `npx tsx orchestrator/ci-parity-check.ts`. It **skips** unless there are parity-relevant seam changes in the git diff AND a manifest is present. Manifests live in `orchestrator/.state/<TICKET>-manifest.json` (gitignored; normally produced at runtime by the cartographer stage).
- To run the verifier locally without the full pipeline: create `orchestrator/.state/MOD-25-manifest.json` (at minimum `{"ticketId":"MOD-25", ...}`; for MOD-25 the harness path/input-shape are auto-pinned) and run with `PARITY_FORCE=1`.
- The verifier shells into the running web container (`docker compose exec -T web php <harness> ...`), so the Compose stack MUST be up first (`bash .cursor/start.sh`). The MOD-25 harness is `legacy/harness/sla_capture.php`; golden fixtures are in `orchestrator/fixtures/MOD-25/`.
- Never weaken/bypass the gate, invent expected values, or edit fixtures to force a pass.

### Lint / test / build
- There is no real lint or test npm script (`npm test` is a placeholder). `tsc --noEmit` reports pre-existing errors (no `@types/node`; ad-hoc `orchestrator/test-stage*.ts`) — the orchestrator is executed with `tsx`, not compiled, so this is not a gating check. The authoritative check is the parity verifier above (also what CI runs, see `.github/workflows/parity-check.yml`).

### Full automated pipeline (optional)
- `orchestrator/automationWorker.ts` / `listener.ts` need `CURSOR_API_KEY`, `LINEAR_API_KEY`, `GITHUB_REPO_URL`, `GITHUB_DEMO_BRANCH` (optional `SLACK_WEBHOOK_URL`). These are only for the end-to-end agent pipeline, not for running/verifying the app or parity gate locally.
