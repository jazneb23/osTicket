# Setting up osticket-strangler-demo on a new machine

The repo is on branch `strangler/MOD-32`. There's also a modified file,
three new files, and a fixtures directory sitting uncommitted in the working
tree — but **don't commit or carry that across**. It's disposable demo run
state (in-progress MOD-30 extraction artifacts), not work in progress worth
preserving. The right move is to reset it, the same way you would between any
two demo runs.

## Reset the demo before you switch machines

This repo already has a cleanup workflow for exactly this — it's what closes
out a batch of MOD-ticket demo runs. Run it (as `/cleanup` in Cursor, or by
hand per `.cursor/skills/cleanup/SKILL.md`) before switching machines:

- Moves all `MOD-*` Linear issues back to Backlog
- Deletes the pipeline's own comments on those tickets
- **Closes open `strangler/MOD-*` PRs** and deletes both the remote and local
  `strangler/MOD-*` branches
- Removes the untracked extraction artifacts — `orchestrator/.state/MOD-*`,
  untracked `orchestrator/fixtures/MOD-*`, untracked
  `orchestrator/manifests/MOD-*`, untracked files under
  `include/Services/` and `legacy/harness/`
- Resets git to `develop`, **preserving** the two committed baselines
  (MOD-25 golden path, MOD-27 Blocked-flow pin) — it never touches those

The short version of the PR/branch step, if you want to run it directly:

```bash
gh pr list --repo OWNER/REPO --state open --limit 100 \
  --json number,headRefName,url | jq -r '.[] | select(.headRefName | test("^strangler/MOD-"))'
# for each match:
gh pr close <number> --repo OWNER/REPO --comment "Demo cleanup — closing E2E discard PR."
git fetch origin
for b in $(git branch -r | sed 's|origin/||' | grep -E '^strangler/MOD-[0-9]+$'); do
  git push origin --delete "$b"
done
```

After cleanup, `git status` is clean on `develop` and there's nothing
uncommitted left to carry over — the new machine just needs a plain clone
plus the two real secrets below. Full detail, including exactly what's
preserved vs. removed, is in `.cursor/skills/cleanup/SKILL.md`; a
local-only/no-Linear-no-GitHub variant is `/rollback` if that's all you need.

## What travels in git

The full osTicket PHP app, the TypeScript orchestrator (`orchestrator/`),
Docker Compose setup, and `AGENTS.md` / `.cursor/skills/onboarding/runbook.md`
— the authoritative references for how this demo runs.

## What does NOT travel, and must be copied

| Path | What it holds | Why it's not in git |
|---|---|---|
| `.env` | `CURSOR_API_KEY`, `LINEAR_API_KEY`, `SLACK_WEBHOOK_URL`, `GITHUB_REPO_URL`, `GITHUB_DEMO_BRANCH`, `LEGACY_APP_URL` | Secrets |
| `include/ost-config.php` | osTicket's runtime config — DB host/user/pass/name and a unique `SECRET_SALT` | Gitignored (osTicket convention); the DB creds match `docker-compose.yml` (`root`/`osticket`) so they're not really secret, but `SECRET_SALT` is unique per install |

That's it once cleanup has run. Everything else that looked uncommitted
(seam manifests, MOD-30 fixtures, the WIP PHP files) is exactly what cleanup
removes — it doesn't need a place to go on the new machine.

## Steps

### 1. Prerequisites

- **Docker Desktop** — runs MySQL 8 + PHP 8.4/Apache via Compose
- **Node** (whatever's current is fine — nothing in this repo pins a version)
- **`gh` CLI**, authenticated (`gh auth login`) — the orchestrator's PR step
  shells out to it
- API keys for Cursor, Linear, Slack, GitHub (in `.env`)

### 2. Clone and install

```bash
cd ~/projects
git clone https://github.com/jazneb23/osTicket.git osticket-strangler-demo
cd osticket-strangler-demo
git checkout develop
npm install
```

Clone onto `develop`, not `strangler/MOD-32` — that branch (and every other
`strangler/MOD-*` branch) gets deleted by the cleanup step above, since it's
per-run demo scratch, not a long-lived branch. The orchestrator creates a
fresh `strangler/MOD-*` branch itself each time it picks up a ticket.

The repo also has an `upstream` remote pointed at the real osTicket project
(`osTicket/osTicket`) — that's for reference/rebasing, not something you need
to configure to get running.

### 3. Bring the two secrets across

```bash
cp osticket-strangler-demo-transfer/.env .env
cp osticket-strangler-demo-transfer/include/ost-config.php include/ost-config.php
```

### 4. Start the local stack

```bash
bash scripts/local-demo-start.sh
```

This checks Docker is running, then bootstraps MySQL 8 (`:3306`) and
PHP 8.4/Apache (`:8080`) via `scripts/ci-docker-bootstrap.sh`. The bootstrap
imports osTicket's core schema and seeds `ost_schedule` rows directly — it
does **not** run the install wizard, so `http://localhost:8080/` will show
"Support Ticket System Offline" and there's no staff login. That's expected;
`:8080` is an optional host-app UI, not the product flow.

### 5. Confirm the harness works

```bash
docker compose exec -T web php legacy/harness/sla_capture.php \
  '{"start":"2024-01-15T10:00:00-05:00","hours":2,"schedule_id":1}'
```

Should print `output: 2024-01-15T12:00:00`. If this works, web + db + PHP are
all correctly wired.

### 6. Run the orchestrator

```bash
npx tsx orchestrator/listener.ts
```

No build step — `tsx` runs TypeScript directly (`tsc --noEmit` is **not**
part of the workflow; it fails on this repo for unrelated legacy-file
reasons). Then move a `MOD-*` ticket to Ready in Linear to trigger the
pipeline.

## Verify the move worked

- Step 5's harness command returns the expected timestamp
- `git status` is clean on `develop`
- `orchestrator/listener.ts` starts without complaining about missing env vars

## Things worth knowing

- **Don't commit `orchestrator/fixtures/*/parity.json`** — it's a generated
  report, regenerated by the verifier each run.
- If you're running as a nested Cursor cloud agent (`withCloudAgent`), none of
  the Docker/local-demo steps apply — see `AGENTS.md` for that path
  separately. This setup.md covers the local/operator-Mac path only.
- If parity checks fail with Docker-compose-shaped errors, re-run
  `bash scripts/local-demo-start.sh` and confirm Docker Desktop is actually
  running before digging further.
