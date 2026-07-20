---
name: onboarding
description: >-
  Onboard engineers to the full repo: the stock osTicket PHP helpdesk app
  (client portal, staff control panel, API, ORM, domain classes, templates,
  plugins) plus the strangler-fig migration demo layered on top (Cursor SDK
  orchestrator, pipeline stages, seams, fixtures, parity gate). Use when the
  user asks to onboard, get a refresher, understand the repo, explain the
  architecture, tour the codebase, or learn how the app or the orchestrator /
  parity gate works.
---

# Onboarding — full repo

Produce a **new-engineer briefing for the actual codebase** a new hire will
spend their time in: the stock osTicket PHP helpdesk app. This means real
code — domain classes, the ORM, request flow, security helpers, extension
points — not a directory listing. There is also a small **strangler-fig
migration demo** (Cursor SDK orchestrator) layered on top; mention it as a
minor, clearly-separated footnote, not a co-equal section. Most of a new
engineer's onboarding value is in `host-app.md`.

Prefer the knowledge in this skill and its references; **spot-check the live
tree** before stating paths, versions, line numbers, or commands as fact —
line numbers especially drift as the file changes. See
[pitfalls.md](pitfalls.md) for past drift examples (a ticket id and a
manifests directory referenced in these docs that no longer existed).

Root `README.md` **is** legitimate architecture context here (it's the
upstream osTicket app README) — unlike the demo-only pipeline details, which
it does not cover at all.

## What this repo is

**osTicket** — a widely-used open-source support ticket system (client portal
+ staff control panel + API), currently `1.18-git` per `bootstrap.php`, forked
from `osTicket/osTicket`. Classic procedural-entry-point + class-file PHP: no
Composer for the app itself, no framework, a custom Django-style ORM, plain
PHP templates (no Smarty/Twig). **This is the app new engineers will actually
read and write code in.**

A small **strangler-fig migration demo** is layered on top, in its own
clearly separated surfaces (`orchestrator/`, `include/Services/`,
`legacy/harness/`). It shows an automated, agent-driven way to modernize
legacy code — a TypeScript orchestrator drives Cursor SDK agents that extract
logic from legacy PHP into thin services and prove behavioral parity before
opening a PR. Treat it as a footnote in most onboarding briefings unless the
user specifically asks about it.

## Output

Default to a **chat walkthrough only** — it's fast and usually sufficient.

Only build/refresh the **canvas** when the user explicitly asks for a visual
artifact (e.g. "make a canvas", "show me visually"), or for a **deep** dive
where a diagram genuinely helps. Building a canvas is not free: it means
writing a full `.canvas.tsx` component and running a live TypeScript check
against it, redone in full each time (there is no incremental/cached path
just because a canvas already exists) — don't pay that cost by default.

If a canvas is requested, read the canvas skill first, name it
`onboarding.canvas.tsx` (refresh if it already exists), and write it to the
workspace canvases directory (see canvas skill for the exact path). Link it
with a markdown link using the full absolute path.

## Progressive disclosure

Read these as needed (one level deep):

| File | When |
|------|------|
| [host-app.md](host-app.md) | **Primary reference.** Real code patterns (ORM, controllers, globals, security, signals, write paths, forms, files, email, cron, validation) + workflow recipes (add an admin page, add a migration, custom lists) + domain classes, DB, templates, plugins, testing, conventions |
| [architecture.md](architecture.md) | Repo-wide directory map, request lifecycle, dual nature (host app vs demo) |
| [runbook.md](runbook.md) | Install stock osTicket, day-to-day commands, CI; demo commands are a small subsection |
| [pitfalls.md](pitfalls.md) | Sacred rules, Cursor rules map, common pitfalls for both surfaces |
| [demo-overlay.md](demo-overlay.md) | Strangler-fig demo detail — only read when the user asks about the demo/pipeline/parity gate specifically |

## Workflow

Copy this checklist and complete it in order:

```
Onboarding briefing:
- [ ] 1. Load skill references + spot-check tree
- [ ] 2. Confirm host-app architecture + demo pipeline/seams
- [ ] 3. Confirm how-to-run + env names for both surfaces
- [ ] 4. Chat deliverable (+ canvas only if requested)
```

### 1. Load references + spot-check

In parallel when possible:

- Read [host-app.md](host-app.md) (primary), [architecture.md](architecture.md),
  [runbook.md](runbook.md), [pitfalls.md](pitfalls.md)
- Spot-check the real code behind `host-app.md`'s excerpts — at minimum
  `include/class.ticket.php` (`$meta`, `postReply`), `main.inc.php` (globals),
  and one `scp/*.php` controller. Re-quote with fresh line numbers if they've
  moved; don't ship stale line numbers.
- Only if the user asks about the demo: skim `orchestrator/pipeline.ts`,
  `orchestrator/lib/types.ts`, `.cursor/rules/*.mdc`,
  [demo-overlay.md](demo-overlay.md)

### 2. Confirm architecture + real patterns

From live files, note:

- Request lifecycle for client / staff / API / setup surfaces
- Any drift in version, fork remotes, or class list vs. `host-app.md`
- Whether the quoted ORM / globals / signals / security patterns in
  `host-app.md` still match the live file at that line — PHP files here get
  edited by ticket work, so line numbers are the most likely thing to drift

### 3. Confirm run + env

From `README.md` (stock install), `package.json`, `docker-compose.yml`, and
`scripts/`:

- Stock osTicket install/upgrade path (installer wizard, requirements)
- The one Docker command that boots the app for local reading/debugging
- Demo commands only if relevant to the ask

### 4. Deliverable

#### Chat walkthrough (default)

1. **What this is** — one sentence on osTicket; one clause on the demo footnote
2. **Where to look first** — 4–6 real host-app paths/classes, with what each does
3. **A real code pattern** — quote one concrete excerpt from `host-app.md`
   (ORM query, controller flow, or signal) so the briefing teaches actual
   code, not just file locations
4. **How to run something** — one command to boot the app locally
5. **Do not break these** — host-app conventions/gotchas first; demo parity
   gate only if relevant
6. Offer the canvas as an option rather than building it unprompted

#### Canvas (only if requested)

Suggested composition (omit empty sections; embed live findings). Give the
host app the majority of the canvas; the demo gets one small section:

- **Header**: one-line what this repo is
- **Request lifecycle**: client/staff/API/setup chains
- **Core classes + real code patterns**: ORM `$meta`/QuerySet, controller
  flow, globals, security helpers, signals — with real excerpts, not just
  file names
- **How to run**: the host-app boot command, plus 1-2 demo commands at most
- **Demo overlay (small)**: one compact section — what it is, where it lives,
  the parity gate in one sentence
- **Gotchas**: host-app conventions first (ORM/legacy SQL mix, timezone
  helpers, no PHPUnit), demo parity gate last

Follow canvas design rules: theme tokens only, no gradients/emojis/shadows,
mix open sections with cards, no empty placeholder UI.

## Depth

| Mode | When | Deliver |
|------|------|---------|
| **quick** | "quick tour" / "refresher" | 5-bullet chat, almost entirely host app; one demo bullet |
| **medium** (default) | onboard / understand repo | Structured chat with at least one real code excerpt; demo as a closing footnote |
| **deep** | "deep dive" / "every class" / "how do I..." | Walk multiple `host-app.md` patterns in depth (ORM, security, signals, write paths); canvas encouraged; demo only if asked |

## Anti-patterns

- Treating this as a demo-only repo, or giving the demo equal billing with
  the host app — most engineering happens in the osTicket codebase
- Listing directories/classes without ever quoting real code — the point of
  this skill is to teach patterns an engineer can act on, not a file index
- Shipping stale line numbers — re-check them against the live file rather
  than trusting `host-app.md` verbatim, since ticket work moves lines around
- Treating the demo's process rules (parity gate, manifest-driven scope) as
  if they apply to ordinary host-app work — they only govern the strangler
  migration surfaces named in `.cursor/rules/repo-context.mdc`
- Inventing classes, patterns, line numbers, pipeline stages, or env vars not in the tree
- Building a canvas by default when a fast chat answer would do
- Dumping huge file trees or full fixture JSON into chat
- Writing secrets from `.env` into canvas or chat
- Editing fixtures or inventing `expected` values to "explain" a pass
