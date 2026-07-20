---
name: cleanup
description: >-
  MOD-tickets-only demo reset: move MOD-* Linear issues to Backlog, delete
  pipeline comments on those tickets, close/delete strangler/MOD-* PRs and
  branches, and remove untracked MOD extraction artifacts. Preserves committed
  MOD-25 golden and MOD-27 Blocked-flow pin on develop. Does not touch non-MOD
  Linear issues or unrelated branches/PRs. Use when the user asks to cleanup
  MOD tickets, reset the demo queue, clear pipeline runs, or invokes /cleanup.
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
| `orchestrator/.state/MOD-*`, untracked `orchestrator/fixtures/MOD-*`, untracked services/harnesses | Committed baselines on `develop` (MOD-25, MOD-27 pin); unrelated repo files |

If the user says "cleanup everything in Linear," clarify: **`/cleanup` is MOD
tickets only** unless they explicitly expand scope.

## Hard rules

- **MOD tickets only** — filter every Linear/GitHub/git step with `MOD-*` /
  `strangler/MOD-*` patterns; never bulk-update the whole workspace.
- **Confirm intent once** before any destructive step (Linear status, comment
  deletion, PR close, branch delete, `git reset --hard`, file removal).
- **Never skip the parity gate** by editing golden `expected` values.
- **Preserve committed baselines on `develop`** (never delete or edit these):
  - **MOD-25** golden path: `orchestrator/fixtures/MOD-25/`,
    `legacy/harness/sla_capture.php`
  - **MOD-27** Blocked-flow pin: `orchestrator/fixtures/MOD-27/`,
    `legacy/harness/overdue_capture.php`,
    `include/Services/TicketOverdueService.php`, and the seeded
    `include/class.ticket.php` facade (intentional parity bug for demo)
- **Never delete tracked files** that exist on `origin/develop`. After
  `git reset --hard`, step 7 may only remove **untracked** runtime junk.
  If a removal would show up as `deleted:` in `git status`, do not do it —
  restore instead.
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
> those branches, and remove **untracked** MOD harness/service/fixture artifacts
> (keeping the committed MOD-25 golden path and MOD-27 Blocked-flow pin on
> develop). Non-MOD Linear issues and PRs are untouched. Proceed?

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

Step 6 already restored every **tracked** file on `develop`, including the
MOD-25 and MOD-27 baselines. This step only clears **untracked** pipeline
output. Do **not** `rm -rf include/Services/`, wipe `MOD-27` fixtures, or
delete `overdue_capture.php` / `TicketOverdueService.php`.

Preserved on `develop` (leave alone — never edit `expected` values):

| Path | Why |
|------|-----|
| `orchestrator/fixtures/MOD-25/` | Golden parity baseline |
| `legacy/harness/sla_capture.php` | MOD-25 harness |
| `orchestrator/fixtures/MOD-27/` | Blocked-flow pin (intentional 5/6 fail) |
| `legacy/harness/overdue_capture.php` | MOD-27 harness |
| `include/Services/TicketOverdueService.php` | Seeded buggy service for Blocked demo |
| `include/class.ticket.php` | Seeded facade for that pin |

Remove runtime state (gitignored manifests / attempt counters):

```bash
rm -f orchestrator/.state/MOD-*-manifest.json
rm -f orchestrator/.state/MOD-*-attempts.json
```

Remove **untracked** fixture dirs only (skip committed MOD-25 / MOD-27):

```bash
for d in orchestrator/fixtures/MOD-*/; do
  [ -d "$d" ] || continue
  id=$(basename "$d")
  case "$id" in MOD-25|MOD-27) continue ;; esac
  # Skip if this tree has any files tracked on develop
  if [ -n "$(git ls-files "$d")" ]; then
    echo "skip tracked fixture dir: $id"
    continue
  fi
  rm -rf "$d"
done
```

Remove verifier-generated parity reports (gitignored; safe to delete):

```bash
find orchestrator/fixtures -name parity.json -delete
```

Remove **untracked** services only — keep `TicketOverdueService.php`:

```bash
mkdir -p include/Services
find include/Services -maxdepth 1 -type f -name '*.php' | while read -r f; do
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    continue
  fi
  rm -f "$f"
done
```

Remove **untracked** harness scripts only — keep `sla_capture.php` and
`overdue_capture.php`:

```bash
find legacy/harness -maxdepth 1 -name '*.php' | while read -r f; do
  base=$(basename "$f")
  case "$base" in sla_capture.php|overdue_capture.php) continue ;; esac
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    continue
  fi
  rm -f "$f"
done
```

Scoped clean for leftover untracked junk (does not touch tracked files):

```bash
git clean -fd -- orchestrator/.state
# Only clean untracked files under Services/harness/fixtures — never -x
git clean -fd -- include/Services legacy/harness orchestrator/fixtures
```

Do **not** run repo-root `git clean -fd` without path scope.

After this step, `git status` must be **clean**. If anything shows as
`deleted:`, you removed a tracked baseline — run `git restore -- <path>`
immediately and fix the skill commands before continuing.

### 8. Verify + report

Run in parallel:

```bash
git status   # must be clean — no deleted: tracked baselines
git branch -a | grep strangler/MOD || echo "(no strangler/MOD branches)"
ls orchestrator/fixtures/   # expect MOD-25 and MOD-27
ls legacy/harness/          # expect sla_capture.php and overdue_capture.php
ls include/Services/ 2>/dev/null || true   # expect TicketOverdueService.php
ls orchestrator/.state/ 2>/dev/null || true
test -f include/Services/TicketOverdueService.php
test -d orchestrator/fixtures/MOD-27
```

Optionally re-list Linear issues to confirm all MOD tickets are Backlog.

## Chat output

Keep the reply short:

1. Tickets moved to Backlog (count + identifiers)
2. Pipeline comments deleted (count per ticket or total)
3. PRs closed (numbers + URLs)
4. Branches deleted (local + remote)
5. Local artifacts removed (paths)
6. What was **preserved** (MOD-25 golden, MOD-27 Blocked pin, `develop`)
7. Next step: move desired tickets to **Ready** and restart listener

## Pipeline comment markers (reference)

| Marker | When posted |
|--------|-------------|
| `## Pipeline complete` | Success → In Review |
| `## Pipeline failed` | Stage throw → back to Ready (or Blocked after retry cap) |
| `## Parity gate failed` | Verifier mismatch → Blocked |
| `## Publish / PR step failed` | Post-parity git/gh failure → Blocked |

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
- Removing the MOD-27 Blocked-flow pin (`fixtures/MOD-27/`,
  `overdue_capture.php`, `TicketOverdueService.php`, or the seeded
  `class.ticket.php` facade) or "fixing" its intentional parity bug
- `rm -rf include/Services/` (wipes the committed MOD-27 service)
- Deleting any path that then appears as `deleted:` in `git status`
- Force-pushing or deleting non-`strangler/MOD-*` branches
- Whole-repo `git clean -fd` without confirmation
- Running cleanup while the listener is still claiming Ready tickets
