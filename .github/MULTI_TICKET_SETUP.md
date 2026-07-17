# Ticket queue setup (one pipeline at a time)

Use this checklist so several Linear **Ready** tickets can sit in a queue and
be processed **serially** — one strangler pipeline at a time — each finishing
with its own PR.

This is the demo / production shape for this repo: a single worker drain, not
concurrent multi-ticket Automations fighting over the same story.

## How serial runs isolate

| Layer | Isolation |
|-------|-----------|
| Compute | One active pipeline at a time (Automation claim lock, or listener `pipelineBusy`) |
| Git remote | Per-ticket branch `strangler/MOD-*` (orchestrator creates at pipeline start) |
| PR target | Base branch from `GITHUB_DEMO_BRANCH` (e.g. `develop`) |
| Fixtures / state | Keyed by ticket id under `orchestrator/fixtures/MOD-*/` |

The orchestrator **never** pushes to the base branch. Only `strangler/MOD-*`
refs receive commits.

## Queue behavior

1. Move **one** ticket to **Ready** (demo: do not batch several at once).
2. Linear **status changed → Ready** fires the Automation.
3. The worker claims that ticket → **In Progress** only when none are already In Progress.
4. Pipeline runs to PR / In Review (or failure comment).
5. Move the next ticket to **Ready** when you want the next run.

Do **not** use a 2‑minute schedule — every tick is a billed cloud agent, even when idle.

### Cursor Automation (preferred)

**Trigger:** Linear → **Status changed** → **Ready** (team/project for MOD tickets).

**Prompt (keep it short — claim lock lives in code):**

```text
Run the strangler worker for jazneb23/osTicket on develop.

1. bash .cursor/start.sh
2. npx tsx orchestrator/automationWorker.ts --max 1

Rules:
- Do not set DOCKER_HOST or troubleshoot docker manually.
- Do not use Cursor’s Create PR tool — the pipeline opens PRs via the cloud pr-agent.
- Do not run pipeline.ts directly — the worker owns claim + run.
- If the worker logs "idle" or "skipping", exit successfully (another run is active or queue is empty).
- If start.sh or the worker fails, report the error and stop.
```

Do not use Cursor’s built-in Create PR action. Turn Create PRs to Off / Never.
The pipeline opens PRs via the cloud pr-agent against jazneb23/osTicket.

**One-time environment checklist (avoid repeated Setup Agent burns):**

1. Environment must be built from repo `.cursor/Dockerfile` + `environment.json`
   on `develop` — not a stale personal snapshot.
2. In Cloud Agents → Environments for this repo: remove any **personal override**
   snapshot so the Dockerfile is authoritative.
3. After changing `.cursor/Dockerfile` or `start.sh`, run **Start Setup Agent**
   **once**, wait until it finishes, then test Automation.
4. If start fails with “Docker CLI missing”, the VM fell back to the default
   image — rebuild from the Dockerfile; do not keep retrying tickets.

### Local listener (debug only)

`orchestrator/listener.ts` uses the same `claimNextReadyTicket` helper and runs
**one pipeline at a time**. Do **not** run the listener while the Linear
Automation is active.

## Required secrets (Automation / cloud agent)

Mirror these from local `.env` into the Cursor Automation environment:

| Secret | Purpose |
|--------|---------|
| `CURSOR_API_KEY` | SDK agents (cartographer, fixture-generator, pr-agent are cloud) |
| `GITHUB_REPO_URL` | Cloud agent repo + git remote |
| `GITHUB_DEMO_BRANCH` | **Required** — PR base branch (not the push target) |
| `LINEAR_API_KEY` | Ticket status / comments |
| `SLACK_WEBHOOK_URL` | Optional PR-opened notification |

### GitHub token permissions

The cloud VM must authenticate `git push` and nested cloud agents must be able
to open PRs with `gh`. Typical setups:

- **Fine-grained PAT** or **classic PAT** with `contents: write` and
  `pull_requests: write` on this repository, **or**
- **GitHub App** installation with the same scopes.

Verify the automation identity can:

1. Create and push refs matching `strangler/*`
2. Open PRs against the base branch on **jazneb23/osTicket** (not upstream)
3. Trigger Actions on those PRs (default for same-repo PRs)

## Recommended branch protection (base branch)

Protect the demo base branch (e.g. `develop`):

1. **Settings → Branches → Add rule** for the base branch pattern.
2. Require status check **Parity Check** (`.github/workflows/parity-check.yml`).
3. Disable force-push on the base branch.
4. Optionally disallow direct pushes so all changes land via PR.

## Merge strategy when facades overlap

If two tickets patch the same `facadeFile` (e.g. `include/class.sla.php`):

- Process them **one at a time** through the queue.
- Merge the first PR before (or rebase the second onto) the updated base.

## Branch cleanup (optional)

After merge, delete remote `strangler/MOD-*` branches via:

- GitHub **Automatically delete head branches** on merged PRs, or
- A scheduled workflow / manual cleanup.

## Ops rules

- **Automation XOR listener** — do not run `orchestrator/listener.ts` while
  the Linear Automation is active.
- Move **one** ticket to **Ready** at a time → one pipeline → one PR →
  Linear **In Review**. Repeat for the next ticket when ready.
