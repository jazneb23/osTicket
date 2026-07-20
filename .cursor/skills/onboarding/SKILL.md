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

Produce a **new-engineer briefing** that covers both surfaces of this repo:

1. **osTicket** — the stock PHP helpdesk app (client portal, staff CP, API,
   ORM, domain classes). This is the legacy codebase engineers read and
   change day to day.
2. **Strangler-fig demo** — a Cursor SDK orchestrator that incrementally
   modernizes that legacy PHP: extract logic into thin services, patch
   facades to delegate, and prove behavioral parity before opening a PR.

Give both surfaces real weight in the briefing. Prefer the knowledge in this
skill and its references; **spot-check the live tree** before stating paths,
versions, line numbers, or commands as fact — line numbers especially drift.
See [pitfalls.md](pitfalls.md) for past drift examples.

Root `README.md` **is** legitimate architecture context for the host app —
it does not cover the demo overlay.

## What this repo is

**osTicket** — a widely-used open-source support ticket system (client portal
+ staff control panel + API), currently `1.18-git` per `bootstrap.php`, forked
from `osTicket/osTicket`. Classic procedural-entry-point + class-file PHP: no
Composer for the app itself, no framework, a custom Django-style ORM, plain
PHP templates (no Smarty/Twig).

**Strangler-fig overlay** — lives in clearly separated surfaces
(`orchestrator/`, `include/Services/`, `legacy/harness/`). A TypeScript
orchestrator drives Cursor SDK agents that modernize **legacy code** by
lifting logic into services and requiring a golden-fixture parity gate before
a PR. Do **not** name specific ticket ids (e.g. `MOD-*`) in the briefing —
speak in terms of legacy seams, facades, harnesses, and the parity gate.

## Output

Always deliver **both**:

1. A **chat walkthrough** (on screen)
2. An **[onboarding canvas](onboarding.canvas.tsx)** beside the chat

Every briefing (chat **and** canvas) must lead with a **Focus first** section
(see below). Building/refreshing the canvas means writing
`onboarding.canvas.tsx` under the workspace canvases directory (see the
canvas skill for the exact path) and linking it with a markdown link using
the full absolute path. Read the canvas skill before writing or editing the
canvas.

### Focus first (required — chat and canvas)

Lead every deliverable with this section. It orients the engineer before any
deeper tour:

- **Where to click first** — staff control panel login:
  `http://localhost:8080/scp/login.php`
- **What the repo is** — one or two sentences: osTicket legacy PHP helpdesk
  + strangler-fig demo that modernizes that legacy code via agents + parity
- **What to explore next** — 3–5 concrete next steps (boot Docker if needed,
  open key host-app files, skim orchestrator stages) without naming ticket ids

In chat, put **Focus first** at the top as a short, scannable block (heading
+ bullets or short paragraphs). In the canvas, put it as the first visual
section after the title — prominent callout or card with the URL as a
clickable `Link`.

## Progressive disclosure

Read these as needed (one level deep):

| File | When |
|------|------|
| [host-app.md](host-app.md) | **Primary host-app reference.** Real code patterns (ORM, controllers, globals, security, signals, write paths, forms, files, email, cron, validation) + workflow recipes + domain classes, DB, templates, plugins, testing, conventions |
| [architecture.md](architecture.md) | Repo-wide directory map, request lifecycle, dual nature (host app vs demo) |
| [runbook.md](runbook.md) | Install / day-to-day commands, CI; demo commands |
| [pitfalls.md](pitfalls.md) | Sacred rules, Cursor rules map, common pitfalls for both surfaces |
| [demo-overlay.md](demo-overlay.md) | Strangler-fig pipeline detail — stages, parity gate, harnesses (omit specific ticket ids from the user-facing briefing) |

## Workflow

Copy this checklist and complete it in order:

```
Onboarding briefing:
- [ ] 1. Load skill references + spot-check tree
- [ ] 2. Confirm host-app architecture + demo pipeline/seams
- [ ] 3. Confirm how-to-run + env names for both surfaces
- [ ] 4. Chat deliverable with Focus first + canvas refresh
```

### 1. Load references + spot-check

In parallel when possible:

- Read [host-app.md](host-app.md) (primary), [architecture.md](architecture.md),
  [runbook.md](runbook.md), [pitfalls.md](pitfalls.md), and skim
  [demo-overlay.md](demo-overlay.md)
- Spot-check the real code behind `host-app.md`'s excerpts — at minimum
  `include/class.ticket.php` (`$meta`, `postReply`), `main.inc.php` (globals),
  and one `scp/*.php` controller. Re-quote with fresh line numbers if they've
  moved; don't ship stale line numbers.
- Spot-check demo surfaces: `orchestrator/pipeline.ts`,
  `orchestrator/lib/types.ts`, `include/Services/`, `legacy/harness/`

### 2. Confirm architecture + real patterns

From live files, note:

- Request lifecycle for client / staff / API / setup surfaces
- Any drift in version, fork remotes, or class list vs. `host-app.md`
- Whether the quoted ORM / globals / signals / security patterns in
  `host-app.md` still match the live file at that line
- Pipeline stage order and parity-gate behavior (without citing ticket ids)

### 3. Confirm run + env

From `README.md` (stock install), `package.json`, `docker-compose.yml`, and
`scripts/`:

- The Docker command that boots the app (`bash scripts/local-demo-start.sh`)
- Staff UI URL: `http://localhost:8080/scp/login.php`
- Demo listener / parity commands only as needed for the briefing depth

### 4. Deliverable

#### Chat walkthrough (always)

1. **Focus first** — required section (URL + orientation + next steps)
2. **What this is** — osTicket legacy app; strangler overlay that modernizes
   legacy code with agents and a parity gate
3. **Where to look in the host app** — 4–6 real paths/classes, with what
   each does
4. **A real code pattern** — quote one concrete excerpt (ORM query,
   controller flow, or signal) so the briefing teaches actual code
5. **Strangler project** — where it lives (`orchestrator/`,
   `include/Services/`, `legacy/harness/`); high-level flow (cartograph →
   harness/fixtures → extract → facade patch → verify → PR); sacred parity
   gate in one sentence. **No ticket ids.**
6. **How to run** — boot command + staff login URL reminder
7. **Do not break these** — host-app conventions; never skip/weaken the
   parity verifier on demo work
8. Link the canvas with its full absolute path

#### Canvas (always — refresh `onboarding.canvas.tsx`)

Suggested composition (omit empty sections; embed live findings):

- **Header**: one-line what this repo is (legacy osTicket + strangler demo)
- **Focus first**: prominent section with
  `http://localhost:8080/scp/login.php` as a `Link`, plus short orientation
  and next steps
- **Host app**: request lifecycle, core classes, one real code pattern block
- **Strangler overlay**: surfaces, stage flow in plain language, parity gate
  — **no ticket ids**
- **How to run**: boot command + staff URL
- **Gotchas**: host-app conventions; demo parity gate; `:8080` may look
  offline / login has no seeded accounts (expected for this demo bootstrap)

Follow canvas design rules: theme tokens only, no gradients/emojis/shadows,
mix open sections with cards, no empty placeholder UI.

## Depth

| Mode | When | Deliver |
|------|------|---------|
| **quick** | "quick tour" / "refresher" | Focus first + short chat bullets on both surfaces; still refresh canvas |
| **medium** (default) | onboard / understand repo | Full chat structure above + canvas; at least one real code excerpt |
| **deep** | "deep dive" / "every class" / "how do I..." | Walk multiple `host-app.md` patterns; expand strangler stages from `demo-overlay.md` (still no ticket ids) |

## Anti-patterns

- Skipping **Focus first** or omitting the staff login URL from chat or canvas
- Naming specific migration ticket ids (`MOD-*`) in the user-facing briefing —
  describe legacy seams and the parity gate generically instead
- Treating this as host-app-only **or** demo-only — both belong in the briefing
- Listing directories/classes without ever quoting real code
- Shipping stale line numbers — re-check against the live file
- Treating the demo's process rules (parity gate, manifest-driven scope) as if
  they apply to ordinary host-app edits outside named seam surfaces
- Inventing classes, patterns, line numbers, pipeline stages, or env vars not
  in the tree
- Skipping the canvas when this skill runs — chat and canvas are both required
- Dumping huge file trees or full fixture JSON into chat
- Writing secrets from `.env` into canvas or chat
- Editing fixtures or inventing `expected` values to "explain" a pass
