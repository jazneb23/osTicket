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

# Onboarding — full repo (demo-static)

This is a **demo**. The onboarding briefing and canvas are **frozen artifacts**.
They must stay the same every run. Speed matters: return the fixed briefing
immediately.

Produce a **new-engineer briefing** that covers both surfaces:

1. **osTicket** — stock PHP helpdesk (client portal, staff CP, API, ORM).
2. **Strangler-fig demo** — Cursor SDK orchestrator that extracts legacy logic
   into services and gates PRs on parity.

Do **not** name specific ticket ids (`MOD-*`) — speak in terms of seams,
facades, harnesses, and the parity gate.

Root `README.md` is legitimate host-app context; it does not cover the demo.

## Demo rule (sacred)

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
Onboarding (demo-static):
- [ ] 1. Emit the frozen chat briefing below (verbatim structure + facts)
- [ ] 2. Link the existing canvas — do not open, edit, or rewrite it
```

**Do not** use TodoWrite, Task, Shell, Grep, Read, or Write for the default
path. No tool calls. Reply from this skill alone.

### Canvas link (always)

Link with the full absolute path (adjust only the username segment if the
workspace path differs):

`/Users/<user>/.cursor/projects/Users-<user>-projects-osticket-strangler-demo/canvases/onboarding.canvas.tsx`

For this workspace that is:

[/Users/jeremy/.cursor/projects/Users-jeremy-projects-osticket-strangler-demo/canvases/onboarding.canvas.tsx](/Users/jeremy/.cursor/projects/Users-jeremy-projects-osticket-strangler-demo/canvases/onboarding.canvas.tsx)

One short line: they can open it beside the chat. Do not rewrite the file.

### Frozen chat briefing (emit this)

Use this structure and these facts every time. Keep it concise. You may
tighten wording slightly for voice, but do **not** change paths, URLs,
version, stage order, or add newly discovered live-tree details.

#### Focus first

- **Where to click first:** `http://localhost:8080/scp/login.php`
- **What the repo is:** osTicket legacy PHP helpdesk + strangler-fig demo that
  modernizes legacy code via agents and a parity gate
- **What to explore next:**
  1. `bash scripts/local-demo-start.sh`
  2. Open the staff login URL
  3. Read `main.inc.php` + `include/class.ticket.php`
  4. Skim `orchestrator/pipeline.ts`
  5. Open `include/Services/` + a script under `legacy/harness/`

#### What this is

- **osTicket 1.18-git** — client portal, staff CP, API; procedural PHP; custom
  Django-style ORM; plain templates; no app-wide Composer
- **Strangler overlay** — agents cartograph a seam, capture fixtures, extract a
  service, patch the facade, verify parity, then PR

#### Where to look

| Path | Role |
|------|------|
| `bootstrap.php` / `main.inc.php` | Boot + every web request |
| `scp/tickets.php` | Canonical staff controller |
| `include/class.ticket.php` | Central domain model |
| `include/class.orm.php` | ORM (`VerySimpleModel`, `QuerySet`, `Q`) |
| `include/class.sla.php` | SLA / grace-period math |
| `client.inc.php` / `scp/staff.inc.php` | Auth + globals |

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

#### Strangler project

Surfaces: `orchestrator/`, `include/Services/`, `legacy/harness/`.

Flow: cartograph → harness/fixtures → extract → facade patch → verify → PR.

Parity gate: harness output must match golden fixture expecteds before a PR.

#### How to run

```bash
bash scripts/local-demo-start.sh
# optional: npx tsx orchestrator/listener.ts
```

Staff UI: `http://localhost:8080/scp/login.php`  
Demo bootstrap skips the install wizard — offline / no staff accounts is expected.

#### Do not break these

- Host app: 4-space indent; explicit `require`s; ORM and hand-built SQL may
  coexist; prefer `Misc::dbtime()` over raw `time()` for DB datetimes; expect
  `global $thisstaff, $cfg`
- Demo seams: never skip/weaken the parity verifier; never invent fixture
  expecteds; touch only manifest-named files; services must not call back into
  the facade entry they replace

#### Close with canvas link

Link the existing onboarding canvas (path above).

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
| [demo-overlay.md](demo-overlay.md) | Explicit refresh / strangler deep-dive |

## Depth

| Mode | When | Deliver |
|------|------|---------|
| **quick** | "quick tour" / "refresher" | Shorter chat from the same frozen facts + link canvas (no rewrite) |
| **medium** (default) | onboard / understand repo | Full frozen briefing + link canvas (no rewrite) |
| **deep** | "deep dive" / "every class" / "how do I..." | Frozen briefing first, then answer the deep question from references — still **no** canvas rewrite unless they asked to refresh |

## Refresh path (rare)

Only when the user **explicitly** asks to refresh/update/rebuild onboarding:

1. Read references + spot-check the tree (host-app excerpts, pipeline, Services, harnesses)
2. Read the canvas skill, then rewrite
   `onboarding.canvas.tsx` under the workspace `canvases/` directory
3. Emit an updated chat briefing aligned with the new canvas
4. Keep **no ticket ids** in user-facing content

Suggested canvas composition (refresh only): header, Focus first with staff
login `Link`, host app + one code pattern, strangler stages + parity gate,
how to run, gotchas (`:8080` offline expected).

## Anti-patterns

- Spot-checking or rewriting the canvas on a normal `/onboarding` run
- Reading all reference docs "just in case" on the fast path
- Skipping **Focus first** or the staff login URL
- Naming migration ticket ids (`MOD-*`) in the briefing
- Host-app-only or demo-only briefings — both belong
- Inventing paths, stages, or env vars not in the frozen template
- Dumping huge file trees or fixture JSON into chat
- Writing secrets from `.env` into chat or canvas
- Editing fixtures or inventing `expected` values to "explain" a pass
