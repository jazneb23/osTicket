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

1. Put N tickets in **Ready**.
2. Exactly **one** run claims a ticket → **In Progress** and runs the pipeline.
3. Other Ready tickets stay Ready until the active run finishes.
4. Next Ready ticket is claimed, and so on.

### Cursor Automation (preferred)

The Automation prompt must enforce a claim lock:

- If another ticket in the project is already **In Progress**, exit without
  changing this ticket (leave it **Ready**).
- Otherwise claim this ticket (**Ready** → **In Progress**), re-read status,
  and run `npx tsx orchestrator/pipeline.ts <TICKET>` once.
- Do not start a second pipeline while one is active.

### Local listener (debug only)

`orchestrator/listener.ts` already polls Ready tickets and runs **one
pipeline at a time** via `pipelineBusy`. Do **not** run the listener while the
Linear Automation is active.

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
- Move N tickets to **Ready** → they drain **one at a time** → N PRs → N
  Linear **In Review**
