---
name: onboarding
description: >-
  Onboard engineers to the full repo: the stock osTicket PHP helpdesk app
  (client portal, staff control panel, API, ORM, domain classes, templates,
  plugins) plus the strangler-fig migration pipeline (Cursor SDK orchestrator,
  Docker runtime, Aikido, parity gate, GitHub CI, Kody review, PRs). Use when
  the user asks to onboard, get a refresher, understand the repo, tour the
  codebase, or learn how the app or the SDLC works.
---

# Onboarding — full new-engineer briefing

Produce a **new-engineer briefing** that covers both surfaces:

1. **osTicket** — stock PHP helpdesk (client portal, staff CP, API, ORM).
2. **Strangler-fig pipeline** — Cursor SDK orchestrator that extracts legacy
   logic into services and ships only when behavior matches, through a full
   SDLC (Linear → agents → Aikido → Docker parity → GitHub CI + Kody → PR).

`/architecture` is the short **chat-only** SDLC briefing. This skill is the
full pack: frozen chat briefing **plus** the onboarding canvas (architecture
diagram + host-app map + how to run).

Do **not** name specific ticket ids (`MOD-*`) in user-facing content — speak
in terms of seams, facades, harnesses, and the parity gate. Do **not** call
this a demo.

Root `README.md` is legitimate host-app context; it does not cover the
orchestrator.

## Frozen briefing (sacred)

**Do not update onboarding by default.**

| Action | Default `/onboarding` | Only when user explicitly asks to refresh/update |
|--------|----------------------|--------------------------------------------------|
| Spot-check live tree | **No** | Yes |
| Read reference docs | **No** | As needed |
| Read canvas skill | **No** | Yes (before any canvas edit) |
| Rewrite `onboarding.canvas.tsx` | **No** | Yes |
| Emit chat briefing | **Yes** — from the frozen template below | Same template unless refresh changes it |
| Link existing canvas | **Yes** | Yes (after rewrite if refreshing) |

If the user says "refresh onboarding", "update the canvas", or "rebuild from
the tree", then follow [Refresh path](#refresh-path-rare). Otherwise use the
[Fast path](#fast-path-default).

## Fast path (default)

Complete this checklist — and **nothing else**:

```
Onboarding:
- [ ] 1. Emit the frozen chat briefing below (verbatim structure + facts)
- [ ] 2. Link the existing canvas — do not open, edit, or rewrite it
```

**Do not** use TodoWrite, Task, Shell, Grep, Read, or Write for the default
path. No tool calls. Reply from this skill alone.

### Canvas link (always)

Link with the full absolute path (adjust only the username / workspace
segment if the path differs):

`/Users/<user>/.cursor/projects/<workspace>/canvases/onboarding.canvas.tsx`

For this workspace that is:

[/Users/jeremybenza/.cursor/projects/Users-jeremybenza-OsTicket-Strangler-osTicket/canvases/onboarding.canvas.tsx](/Users/jeremybenza/.cursor/projects/Users-jeremybenza-OsTicket-Strangler-osTicket/canvases/onboarding.canvas.tsx)

One short line: they can open it beside the chat. Do not rewrite the file.

### Frozen chat briefing (emit this)

Use this structure and these facts every time. Keep it concise. You may
tighten wording slightly for voice, but do **not** change paths, URLs,
version, stage order, or add newly discovered live-tree details.

#### Focus first

- **Where to click first:** `http://localhost:8080/scp/login.php`
- **What the repo is:** osTicket PHP helpdesk plus a strangler-fig pipeline
  that extracts legacy logic behind stable facades and gates every PR on
  parity, AppSec, CI, and code review
- **What to explore next:**
  1. `bash scripts/local-demo-start.sh` (Docker: MySQL 8 + PHP 8.4/Apache)
  2. Open the staff login URL
  3. Read `main.inc.php` + `include/class.ticket.php`
  4. Skim `orchestrator/pipeline.ts`
  5. Open the onboarding canvas (SDLC diagram)

#### What this is

**Why the orchestrator exists:** a fleet of agents incrementally extracts
and modernizes legacy logic behind a parity-gated facade, so the app is
upgraded piece by piece instead of through a risky full rewrite.

- **osTicket 1.18-git** — client portal, staff CP, API; procedural PHP; custom
  Django-style ORM; plain templates; no app-wide Composer
- **Docker** is the runtime container — Compose `db` + `web` hosts the app
  and every harness / parity / CI run

#### SDLC

Linear **Ready** → listener claims one ticket (**In Progress**) → agent
workflow top to bottom: **1 Cartographer** (cloud) → **2a Harness builder**
→ **2b Fixture generator** (cloud) → **2c Baseline capture** (Docker) →
**3 Extractor** → **4 Strangler** → **4b Aikido Sentinel** (secrets halt,
no PR; SAST continues) → **5 Verifier** (Docker parity) → **6 PR agent**
(local `gh`) → GitHub checks in parallel (**Parity CI**, **Aikido PR
Checks**, **Kody** comments; auto-approve off) → Linear **In Review**.
Secrets or parity fail → **Blocked**. A human is the only approver.

#### Where to look

| Path | Role |
|------|------|
| `bootstrap.php` / `main.inc.php` | Boot + every web request |
| `scp/tickets.php` | Canonical staff controller |
| `include/class.ticket.php` | Central domain model |
| `include/class.orm.php` | ORM (`VerySimpleModel`, `QuerySet`, `Q`) |
| `include/class.sla.php` | SLA / grace-period math |
| `client.inc.php` / `scp/staff.inc.php` | Auth + globals |
| `docker-compose.yml` | Runtime: MySQL 8 + PHP 8.4/Apache |
| `orchestrator/pipeline.ts` | Stage order + gates |
| `.github/workflows/parity-check.yml` | What CI runs on the PR |
| `kodus-config.yml` / `.kody/rules/` | Kody review (comments only) |

#### Real code pattern (frozen excerpt)

Quote this shape (do not re-open the file to "confirm" line numbers on the
fast path). Attribute it to `include/class.ticket.php` static `$meta`:

```php
static $meta = array(
    'table' => TICKET_TABLE,
    'pk' => array('ticket_id'),
    'select_related' => array('topic', 'staff', 'user', 'team', 'dept',
        'sla', 'thread', 'child_thread', 'user__default_email', 'status'),
    'joins' => array(
        'user' => array(
            'constraint' => array('user_id' => 'User.id'),
            'null' => true,
        ),
        'status' => array(
            'constraint' => array('status_id' => 'TicketStatus.id')
        ),
        // …
    ),
);
```

Staff controllers: require auth → lookup + ACL → POST dispatch → wrap a
`.inc.php` in header/footer (`scp/tickets.php`).

#### How to run

```bash
bash scripts/local-demo-start.sh
npx tsx orchestrator/listener.ts
```

Docker is required for the pipeline, not optional. Nested cartographer /
fixture agents use Cursor cloud VMs and must not start Docker.

Staff UI: `http://localhost:8080/scp/login.php`  
The Compose bootstrap does not run the install wizard — the portal showing
offline / no staff accounts is expected until you seed them.

#### Do not break these

- Host app: 4-space indent; explicit `require`s; ORM and hand-built SQL may
  coexist; prefer `Misc::dbtime()` over raw `time()` for DB datetimes; expect
  `global $thisstaff, $cfg`
- Pipeline: never skip/weaken the parity verifier; never invent fixture
  expecteds; touch only manifest-named files; services must not call back into
  the facade entry they replace. Kody comments are review, not approval.

#### Close with canvas link

Link the existing onboarding canvas (path above). Tell them the hero is the
SDLC diagram — click a node for what runs there.

## Focus first (required)

Every chat reply must lead with Focus first (URL + orientation + next steps).
The frozen canvas already has the matching section — do not edit it on the
fast path.

## Progressive disclosure (refresh / deep only)

| File | When |
|------|------|
| [host-app.md](host-app.md) | Explicit refresh, or user asks a host-app deep-dive |
| [architecture.md](architecture.md) | Explicit refresh / architecture deep-dive |
| [runbook.md](runbook.md) | Explicit refresh / run commands deep-dive |
| [pitfalls.md](pitfalls.md) | Explicit refresh |
| [demo-overlay.md](demo-overlay.md) | Explicit refresh / pipeline deep-dive |

## Depth

| Mode | When | Deliver |
|------|------|---------|
| **quick** | "quick tour" / "refresher" | Shorter chat from the same frozen facts + link canvas (no rewrite) |
| **medium** (default) | onboard / understand repo | Full frozen briefing + link canvas (no rewrite) |
| **deep** | "deep dive" / "every class" / "how do I..." | Frozen briefing first, then answer the deep question from references — still **no** canvas rewrite unless they asked to refresh |

## Refresh path (rare)

Only when the user **explicitly** asks to refresh/update/rebuild onboarding:

1. Read references + spot-check the tree (`pipeline.ts`, parity workflow,
   `kodus-config.yml`, Docker Compose, host-app excerpts)
2. Read the canvas skill, then rewrite
   `onboarding.canvas.tsx` under the workspace `canvases/` directory
3. Emit an updated chat briefing aligned with the new canvas
4. Keep **no ticket ids** and **no “this is a demo”** in user-facing content

Canvas composition (refresh only):

- Hero: interactive SDLC diagram (`computeDAGLayout`) — Linear → agents →
  Aikido Sentinel → Docker parity → PR → GitHub checks (parity CI, Aikido
  PR Checks, Kody) → In Review. Docker as a labeled band around harness /
  verifier / CI. Click a node for what runs there.
- Then: Focus first with staff login `Link`, host app + one code pattern,
  how to run Docker + listener, sacred gates. `:8080` offline is expected
  until staff is seeded.

## Anti-patterns

- Spot-checking or rewriting the canvas on a normal `/onboarding` run
- Reading all reference docs "just in case" on the fast path
- Skipping **Focus first** or the staff login URL
- Naming migration ticket ids (`MOD-*`) in the briefing
- Calling this a demo
- Host-app-only or pipeline-only briefings — both belong
- Omitting Docker, Sentinel, CI, Aikido PR Checks, or Kody from the SDLC
- Inventing paths, stages, or env vars not in the frozen template
- Dumping huge file trees or fixture JSON into chat
- Writing secrets from `.env` into chat or canvas
- Editing fixtures or inventing `expected` values to "explain" a pass
