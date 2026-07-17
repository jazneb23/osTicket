# Local demo setup (Linear Ready → local listener)

**Parity runs on your machine** via Docker Compose. Cursor cloud Automations do
**not** run this pipeline — cloud Docker-in-Docker has been unreliable for this demo.

Nested SDK stages (cartographer, fixture-generator) use `withCloudAgent` and
appear on cursor.com/agents. The **PR step runs locally** via `gh pr create` on
the machine running the listener (cloud agents lack GitHub PR permissions).
Baseline capture and the verifier call `docker compose exec` on **local** Docker.

## First-time setup (new clone or after `package-lock.json` changes)

```bash
git checkout develop && git pull
npm ci
cp .env.example .env   # if needed — fill CURSOR_API_KEY, LINEAR_API_KEY, GITHUB_*, etc.
```

## Every demo session

```bash
bash scripts/local-demo-start.sh
npx tsx orchestrator/listener.ts
```

**Disable** the Cursor Linear Automation (or any scheduled Automation) so you do
not double-start pipelines.

## During the demo

1. Move **one** `MOD-*` ticket to **Ready** in Linear.
2. Listener claims it → **In Progress** → runs `pipeline.ts`.
3. On parity pass → publish to `strangler/MOD-*` → local `gh` opens PR → Slack → **In Review**.
4. **Demo only:** close the PR and delete the strangler branch after the demo — never merge MOD-* into `develop`.
5. Move the next ticket to Ready when ready.

## Required `.env` keys (local machine)

| Name | Purpose |
|------|---------|
| `CURSOR_API_KEY` | Nested cloud agents |
| `LINEAR_API_KEY` | Claim + status + comments |
| `GITHUB_REPO_URL` | Cloud agent repo |
| `GITHUB_DEMO_BRANCH` | `develop` — PR base (demo PRs never merge here) |
| `gh` CLI | `brew install gh && gh auth login` — opens demo PRs locally |
| `SLACK_WEBHOOK_URL` | Optional PR notification |

## Smoke test (no Linear)

```bash
bash scripts/local-demo-start.sh
npx tsx orchestrator/pipeline.ts MOD-25 --criteria "..."   # or your throwaway MOD-*
```

## Why not cloud Automation?

The parity harness requires MySQL + PHP via Compose (`orchestrator/lib/harness.ts`).
Running that inside Cursor’s cloud VM (Docker-in-Docker) was the Jul 15–17 failure
mode. Local Docker Desktop is stable; the listener gives the same Linear Ready UX.

Cloud nested agents (cartographer, fixture, PR) still wake via
`.cursor/start-cloud-agent.sh` — that script **does not** start Docker.

## Preflight

`listener.ts` and `pipeline.ts` call `assertLocalDockerReady()` before running.
If Docker Desktop is off or Compose is down, you get an immediate error pointing
at `scripts/local-demo-start.sh`.

## Ops

- One Ready ticket at a time (listener claim lock).
- Do **not** run Automation and listener together.
- No Setup Agent / cloud environment rebuild needed for this path.
