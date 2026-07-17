# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **strangler-fig migration demo**: a TypeScript orchestrator
(`orchestrator/`) drives Cursor SDK agents that extract logic from legacy
osTicket PHP into thin services and prove behavioral parity against golden
fixtures before opening a PR. osTicket itself is the *legacy host app* exercised
by the parity harness — not a fully installed help desk. See
`.cursor/skills/onboarding/runbook.md` for the full runbook and
`.cursor/rules/repo-context.mdc` for the sacred constraints (never weaken the
parity gate; leave vendor/unrelated trees alone).

### Environment (already provisioned on startup)

The cloud environment is defined by the repo: `.cursor/environment.json` →
`.cursor/Dockerfile` (Ubuntu 24.04 + Node 22 + Docker CE/Compose in DinD + `gh`),
with `install` = `bash .cursor/install.sh` (`npm ci`) and `start` =
`bash .cursor/start.sh`. Because `environment.json` points at the Dockerfile,
that Dockerfile is the source of truth for system deps — change it there, not via
a snapshot.

### Bringing services up (not done by the dependency update script)

Run `bash .cursor/start.sh` to start the Docker daemon and then
`scripts/ci-docker-bootstrap.sh`, which brings up Compose (`db` = MySQL 8 on
`:3306`, `web` = PHP 8.4 Apache on `:8080`), imports the osTicket schema,
generates the gitignored `include/ost-config.php`, and seeds schedules needed by
the verifier. It is idempotent. If Docker is already running you can instead run
just `bash scripts/ci-docker-bootstrap.sh`.

### Running the parity gate (the demo's core check)

- Command: `npx tsx orchestrator/ci-parity-check.ts` (this is what CI runs).
- Non-obvious: it **skips** unless a cartographer seam manifest is present in
  `orchestrator/manifests/` or `orchestrator/.state/` (`.state/` is gitignored
  runtime output). In a fresh checkout there is no manifest, so it self-skips.
- To run it standalone for MOD-25 without the full pipeline (which needs
  `CURSOR_API_KEY` + Linear/Slack secrets): write a manifest to
  `orchestrator/.state/MOD-25-manifest.json` with at least
  `{"ticketId":"MOD-25","harnessScript":"legacy/harness/sla_capture.php"}` (for
  MOD-25 the harness script/input shape are auto-pinned by `applyHarnessDefaults`)
  and run `PARITY_FORCE=1 npx tsx orchestrator/ci-parity-check.ts`. Expect
  `MOD-25: 6/6 passed — PASSED` on the unmodified baseline.
- The harness runs **inside the `web` container** via
  `docker compose exec -T web php <script>`, so `db` + `web` must be up first
  (run the start script above). Harness scripts bootstrap through `main.inc.php`.

### osTicket web UI (optional)

The app serves at `http://localhost:8080`. The demo bootstrap only imports the
core schema + parity seeds — it does **not** run the full osTicket installer, so
there is no admin/help-topic seed and the public Support Center renders
"Support Ticket System Offline". The agent login (`/scp/login.php`) renders and
confirms DB connectivity. Port `:8080` is not required for the parity flow.

### Lint / build / test

There is **no** `tsc` build or lint step and `npm test` is a placeholder — the
orchestrator runs TypeScript directly via `tsx`, and the parity gate is the real
check. Running `tsc --noEmit` reports errors only because `@types/node` is not a
dependency; it is not part of this project's workflow.
