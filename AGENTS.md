# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **strangler-fig migration demo**: a TypeScript/Node orchestrator
(`orchestrator/`, run via `tsx`) that drives a parity-gated migration of the
legacy **osTicket** PHP app, which runs in Docker Compose (PHP 8.4/Apache `web`
on `:8080` + MySQL 8 `db` on `:3306`). Standard commands and env-var names live
in `.cursor/skills/onboarding/runbook.md` — consult it rather than duplicating.

The notes below are the non-obvious gotchas for running services here.

### Environment build vs. just-in-time pods
- The committed `.cursor/environment.json` builds from `.cursor/Dockerfile`
  (Docker-in-Docker + Node 22 + `gh`), then runs `.cursor/install.sh` (`npm ci`)
  and `.cursor/start.sh`. That Dockerfile is the source of truth for env changes.
- If a pod boots **just-in-time** (no Dockerfile build), `docker` and
  `node_modules` will be missing. Recover by running `npm ci` and installing
  Docker CE with the Cursor DinD workarounds (`storage-driver: fuse-overlayfs`
  in `/etc/docker/daemon.json` + `update-alternatives --set iptables
  /usr/sbin/iptables-legacy`), then `sudo service docker start`.

### Starting services
- `bash .cursor/start.sh` is idempotent: it starts the Docker daemon (widening
  `/var/run/docker.sock` perms if needed) and runs `scripts/ci-docker-bootstrap.sh`
  to bring up Compose and seed schema + `include/ost-config.php` + schedules.
  Prefer it over calling `docker compose up` directly.

### Parity gate (the real test — `npm test` is a placeholder)
- Run `PARITY_FORCE=1 npx tsx orchestrator/ci-parity-check.ts`. Without
  `PARITY_FORCE=1`, on `develop` with no seam-related diff the check
  intentionally **skips** (returns success without verifying).
- Both the CI check and `npx tsx orchestrator/capture-and-verify.ts MOD-25`
  require a seam manifest at `orchestrator/.state/<TICKET>-manifest.json` (or a
  committed one under `orchestrator/manifests/`). In this demo the cartographer
  produces it at runtime, so a **fresh checkout has none** and the verifier
  errors with "No manifest found". To run parity locally without a full pipeline
  run, seed `orchestrator/.state/MOD-25-manifest.json` (any valid `SeamManifest`
  with `ticketId: "MOD-25"`; its `harnessScript` is auto-pinned to
  `legacy/harness/sla_capture.php`). `.state/` is gitignored.

### osTicket web UI (`:8080`) is optional and only partially provisioned
- The bootstrap seeds only what the parity harness needs (schema, config,
  business-hours schedules). It does **not** perform a full osTicket install:
  no help topics/departments/staff, and `isonline`/`schema_signature` are unset,
  so the UI reports "Support Ticket System Offline" until those DB rows are added.
  The core product is the orchestrator + parity gate, not the legacy UI.

### No build/lint gate
- The orchestrator runs `.ts` directly via `tsx`; there is no build step and no
  ESLint config. `tsc --noEmit` is **not** a gate — the `orchestrator/test-stage*.ts`
  debug helpers have pre-existing type errors (no `@types/node`). Do not treat
  those as regressions.
