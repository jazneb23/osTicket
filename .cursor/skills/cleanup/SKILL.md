---
name: cleanup
description: >-
  MOD-tickets-only demo reset: move MOD-* Linear issues to Backlog, delete
  pipeline comments on those tickets, close/delete strangler/MOD-* PRs and
  branches, and remove MOD extraction artifacts. Does not touch non-MOD Linear
  issues or unrelated branches/PRs. Use when the user asks to cleanup MOD
  tickets, reset the demo queue, clear pipeline runs, or invokes /cleanup.
---

# Cleanup — MOD tickets only

Reset the **SLA Modernization `MOD-*`** demo after pipeline runs: those Linear
issues, their pipeline comments, `strangler/MOD-*` PRs/branches, and local MOD
extraction junk. **Out of scope:** any other Linear project, team, issue
identifier, branch, or PR.

This is broader than `/rollback` (which only resets git locally).

## Scope (MOD only)

| In scope | Out of scope |
|----------|--------------|
| Linear issues matching `^MOD-\d+$` in SLA Modernization | Other teams, projects, or identifiers (e.g. `LIN-*`, non-MOD work) |
| Pipeline comments on those MOD issues | Comments on non-MOD issues |
| PRs with head `strangler/MOD-*` | Other PRs or branches |
| Branches matching `strangler/MOD-*` | `develop`, `main`, feature branches, `demo/*`, etc. |
| `orchestrator/.state/MOD-*`, `orchestrator/fixtures/MOD-*` (except preserved MOD-25), MOD-created services/harnesses | Unrelated repo files or non-MOD fixtures |

If the user says "cleanup everything in Linear," clarify: **`/cleanup` is MOD
tickets only** unless they explicitly expand scope.

## Hard rules

- **MOD tickets only** — filter every Linear/GitHub/git step with `MOD-*` /
  `strangler/MOD-*` patterns; never bulk-update the whole workspace.
- **Confirm intent once** before any destructive step (Linear status, comment
  deletion, PR close, branch delete, `git reset --hard`, file removal).
- **Never skip the parity gate** by editing golden `expected` values.
- **Preserve the MOD-25 baseline on `develop`**: committed
  `orchestrator/fixtures/MOD-25/` and `legacy/harness/sla_capture.php` stay.
- **Never delete or overwrite `.env`**.
- **Never force-push `main` / `develop`**.
- Move tickets to **Backlog** — do not Cancel unless the user explicitly asks.
- Delete only **pipeline-added** Linear comments (see markers below), not
  human-written discussion.
- Skip Linear **inline description comments** (`quotedText` non-null) — delete
  only top-level pipeline thread comments.

## Prerequisites

| Need | Why |
|------|-----|
| `LINEAR_API_KEY` | List/update issues, list/delete comments (Linear MCP) |
| `gh auth login` | Close PRs, delete remote branches |
| `GITHUB_REPO_URL` or `gh repo view` | Resolve repo for `gh` |

If the listener is running, tell the user to stop it first (`Ctrl+C` on
`listener.ts`) so nothing re-claims tickets mid-cleanup.

## Workflow

Copy this checklist and tick as you go:

```
Cleanup:
- [ ] 1. Confirm intent
- [ ] 2. Discover MOD tickets (Linear)
- [ ] 3. Move all MOD tickets to Backlog
- [ ] 4. Delete pipeline comments
- [ ] 5. Close PRs + delete strangler branches
- [ ] 6. Reset git to develop
- [ ] 7. Remove local extraction artifacts
- [ ] 8. Verify + report
```

### 1. Confirm intent

If the user has not clearly asked for a full MOD cleanup, ask once:

> This will reset **MOD tickets only** (`MOD-*`): move them to Backlog, delete
> pipeline comments on those issues, close open `strangler/MOD-*` PRs, delete
> those branches, and remove local MOD harness/service/fixture artifacts
> (keeping MOD-25 golden fixtures on develop). Non-MOD Linear issues and PRs
> are untouched. Proceed?

Do not run destructive commands until they confirm (or their message was
already an explicit cleanup / fresh-start request).

### 2. Discover MOD tickets (Linear)

List issues in the **SLA Modernization** project (team **Modernize**):

```text
list_issues { "project": "SLA Modernization", "limit": 250 }
```

Collect **only** issues whose identifier matches `^MOD-\d+$`. Ignore every
other issue in the project or team. Record current status for the summary.

### 3. Move all MOD tickets to Backlog

For each MOD ticket:

```text
save_issue { "id": "MOD-<n>", "state": "Backlog" }
```

Skip tickets already in Backlog. Do not change titles or descriptions unless
the user asked for that separately.

### 4. Delete pipeline comments

For each MOD ticket, paginate comments:

```text
list_comments { "issueId": "MOD-<n>", "orderBy": "createdAt", "limit": 250 }
```

Delete a comment when **all** of the following hold:

- Top-level comment (`quotedText` is null/ absent)
- Body starts with one of these pipeline markers (from `orchestrator/lib/linear.ts`):
  - `## Pipeline complete`
  - `## Pipeline failed`
  - `## Parity gate failed`
  - `## Publish / PR step failed`

```text
delete_comment { "id": "<comment-uuid>" }
```

If deletion fails on an inline thread, skip the root and note it in the report.

### 5. Close PRs + delete strangler branches

Resolve repo:

```bash
# Prefer env; else:
gh repo view --json nameWithOwner -q .nameWithOwner
```

List open PRs whose head branch is `strangler/MOD-*`:

```bash
gh pr list --repo OWNER/REPO --state open --limit 100 \
  --json number,headRefName,url | jq -r '.[] | select(.headRefName | test("^strangler/MOD-"))'
```

For each match:

```bash
gh pr close <number> --repo OWNER/REPO --comment "Demo cleanup — closing E2E discard PR."
```

Delete remote strangler branches (all MOD tickets, including MOD-25):

```bash
git fetch origin
for b in $(git branch -r | sed 's|origin/||' | grep -E '^strangler/MOD-[0-9]+$'); do
  git push origin --delete "$b"
done
```

Also close **already-merged** open PRs if any remain from search — the goal is
no open MOD strangler PRs.

Do **not** delete local strangler branches here — if the operator is still on
one, `git branch -D` refuses to delete the checked-out branch. Local cleanup
happens in step 6 after `git checkout develop`.

### 6. Reset git to develop

Inspect first:

```bash
git status
git branch --show-current
```

Then check out `develop` **before** deleting local strangler branches (Git
cannot delete the branch you are on):

```bash
git checkout develop
```

Delete local strangler branches (portable on macOS and Linux):

```bash
git branch | sed 's/^[* ] //' | grep -E '^strangler/MOD-[0-9]+$' | while read -r b; do
  git branch -D "$b"
done
```

Then reset to the remote baseline:

```bash
git fetch origin develop
git reset --hard origin/develop
```

This drops tracked facade patches and staged extraction files on the former
strangler branch. Untracked artifacts are handled in step 7.

### 7. Remove local extraction artifacts

Remove runtime manifests:

```bash
rm -f orchestrator/.state/MOD-*-manifest.json
```

Remove fixture dirs **except MOD-25 golden** (and never edit MOD-25 JSON):

```bash
for d in orchestrator/fixtures/MOD-*/; do
  id=$(basename "$d")
  [ "$id" = "MOD-25" ] && continue
  rm -rf "$d"
done
```

Remove generated parity reports anywhere:

```bash
find orchestrator/fixtures -name parity.json -delete
```

Remove ticket-created services (none are committed on develop except future
baseline work — today all of `include/Services/` is demo extraction output):

```bash
rm -rf include/Services/
mkdir -p include/Services
```

Keep the directory if the repo expects it; an empty dir is fine.

Remove harness scripts **except the MOD-25 baseline**:

```bash
find legacy/harness -maxdepth 1 -name '*.php' ! -name 'sla_capture.php' -delete
```

Search for stray ticket artifacts and remove if found:

```bash
# Failed cartographer paths, assign captures, etc.
find legacy/harness -name '*_capture.php' ! -name 'sla_capture.php' -delete
```

If `git status` still shows untracked MOD junk under agreed paths, scoped clean:

```bash
git clean -fd -- orchestrator/.state include/Services legacy/harness
```

Do **not** run repo-root `git clean -fd` without path scope.

### 8. Verify + report

Run in parallel:

```bash
git status
git branch -a | grep strangler/MOD || echo "(no strangler/MOD branches)"
ls orchestrator/fixtures/
ls legacy/harness/
ls include/Services/ 2>/dev/null || true
ls orchestrator/.state/ 2>/dev/null || true
```

Optionally re-list Linear issues to confirm all MOD tickets are Backlog.

## Chat output

Keep the reply short:

1. Tickets moved to Backlog (count + identifiers)
2. Pipeline comments deleted (count per ticket or total)
3. PRs closed (numbers + URLs)
4. Branches deleted (local + remote)
5. Local artifacts removed (paths)
6. What was **preserved** (MOD-25 fixtures, `sla_capture.php`, `develop`)
7. Next step: move desired tickets to **Ready** and restart listener

## Pipeline comment markers (reference)

| Marker | When posted |
|--------|-------------|
| `## Pipeline complete` | Success → In Review |
| `## Pipeline failed` | Stage throw → back to Ready |
| `## Parity gate failed` | Verifier mismatch → stays In Progress |
| `## Publish / PR step failed` | Post-parity git/gh failure |

## Relationship to `/rollback`

| Skill | Scope |
|-------|--------|
| `/rollback` | Local git baseline only; optional scoped `git clean` |
| `/cleanup` | Linear + GitHub + all MOD tickets + full extraction artifact sweep |

Use `/cleanup` before a fresh batch of pipeline demos. Use `/rollback` for a
quick local discard without touching Linear or GitHub.

## Anti-patterns

- Touching non-MOD Linear issues, comments, PRs, or branches
- Moving or canceling the whole SLA Modernization project instead of `MOD-*` only
- Canceling tickets instead of Backlog without user request
- Deleting all Linear comments (including human notes)
- Removing MOD-25 golden fixtures or editing their `expected` values
- Force-pushing or deleting non-`strangler/MOD-*` branches
- Whole-repo `git clean -fd` without confirmation
- Running cleanup while the listener is still claiming Ready tickets
