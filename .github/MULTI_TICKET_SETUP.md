# Linear Ready Automation setup (one ticket at a time)

This is the **Jul 15 working shape**: Linear status → Ready starts one cloud
pipeline. Do **not** run concurrent Ready tickets. Do **not** use a cron schedule.

## Flow

1. Move **one** `MOD-*` ticket to **Ready**.
2. Cursor Automation runs on `develop`.
3. Cloud `start.sh` starts Docker + Compose bootstrap.
4. Pipeline runs (`pipeline.ts`) with nested cloud agents.
5. On parity pass → publish → PR → Slack → Linear **In Review**.

## Cursor Automation

| Setting | Value |
|--------|--------|
| Trigger | Linear → Status changed → **Ready** |
| Repo / branch | `jazneb23/osTicket` · `develop` |
| Create pull request | **Off / Never** (pipeline opens PRs via cloud pr-agent) |

**Prompt:**

```text
You are running the strangler pipeline for jazneb23/osTicket on develop.

1. Identify the Linear ticket that just moved to Ready (MOD-*).
2. Move it to In Progress if it is still Ready.
3. Run:
   bash .cursor/start.sh
   npx tsx orchestrator/pipeline.ts <TICKET> --criteria "<ticket description>"
4. Do not set DOCKER_HOST or troubleshoot Docker beyond running start.sh.
5. Do not use Cursor’s Create PR tool — the pipeline opens the PR via the cloud pr-agent.
6. If start.sh or the pipeline fails, report the error and stop.
```

## Secrets

Mirror local `.env` into the Automation / cloud environment:

- `CURSOR_API_KEY`
- `LINEAR_API_KEY`
- `GITHUB_REPO_URL`
- `GITHUB_DEMO_BRANCH=develop`
- `SLACK_WEBHOOK_URL` (optional)

## Ops

- Automation XOR local `listener.ts` — do not run both.
- One Ready ticket at a time.
- No Setup Agent rebuild needed for `start.sh`-only changes.
