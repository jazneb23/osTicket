---
name: architecture
description: >-
  Chat-first architecture briefing of the strangler-fig SDLC: Linear tickets,
  Cursor agents, Docker runtime, Aikido Sentinel, parity gate, GitHub CI,
  Kody review, and PRs. Use when the user asks for architecture, explain the
  system, walk through the pipeline, SDLC, or invokes /architecture.
---

# Architecture — SDLC briefing

Produce a **chat-first** architecture explanation for a first-time engineer.
This skill is **architecture only**. `/onboarding` is the full briefing
(canvas diagram + host-app map + how to run). If they want that pack, tell
them to use `/onboarding`.

Do **not** treat root `README.md` as pipeline architecture (upstream osTicket
only). Do **not** call this a demo.

## Output

Default: **chat narrative only** (no required canvas). Only create a canvas
if the user asks for one.

## Workflow

```
Architecture walkthrough:
- [ ] 1. Spot-check the live tree
- [ ] 2. Load shared refs if helpful
- [ ] 3. Deliver the narrative
```

### 1. Spot-check (required)

In parallel when possible:

- `orchestrator/pipeline.ts` — stage order and gate behavior (includes Sentinel)
- `orchestrator/agents/` — which stages exist
- `orchestrator/lib/types.ts` — `SeamManifest`, `ParityReport`
- `orchestrator/fixtures/`, `include/Services/`, `legacy/harness/` — active
  seams on `develop`. `orchestrator/manifests/` exists only on
  `strangler/MOD-*` PR branches (CI copy); it is not on `develop`
- `.github/workflows/parity-check.yml` — what CI actually runs
- `kodus-config.yml` — Kody comments only; auto-approve off

Confirm stage count and names from code. Do not invent stages. Do not freeze
an old “five stages” story.

### 2. Shared refs (optional)

Reuse, do not fork:

- [../onboarding/architecture.md](../onboarding/architecture.md)
- [../onboarding/demo-overlay.md](../onboarding/demo-overlay.md)
- [../onboarding/pitfalls.md](../onboarding/pitfalls.md)

### 3. Narrative (default shape)

Deliver in this order:

1. **What this is** (1–2 sentences) — osTicket PHP helpdesk plus a strangler-fig
   pipeline that extracts legacy logic behind stable facades
2. **SDLC** — Linear Ready → listener (In Progress) → agents → Aikido Sentinel
   (secrets halt; SAST reported) → parity verifier on Docker → PR via local
   `gh` → GitHub checks in parallel (parity CI, Aikido PR Checks, Kody) →
   Linear In Review. Human is the only approver
3. **Manifest-driven stages** — live names from `pipeline.ts`, listed in
   order: cartographer (cloud) → harness builder → fixture generator
   (cloud) → baseline capture (Docker) → extractor → strangler → Sentinel
   (Aikido MCP) → verifier (Docker) → pr-agent (local `gh`). Later stages
   read `facadeFile`, `extractionTarget`, `harnessScript`. Kody is **not**
   a pipeline stage
4. **Docker** — Compose `db` (MySQL 8) + `web` (PHP 8.4/Apache) is the runtime
   for the app, harness, baseline capture, verifier, and CI parity. Cloud
   cartographer / fixture-generator do not start Docker
5. **Parity gate** — no PR, no Slack success, no Linear In Review unless
   `gatePassed`
6. **Where it lives** — 5–7 real paths from the tree you just checked

Depth:

| Mode | Deliver |
|------|---------|
| **default** | Crisp SDLC architecture (~1 screen of chat) |
| **deep** | Stage-by-stage + concrete seam files (manifest ↔ service ↔ harness) + what each GitHub check proves |

## Anti-patterns

- Requiring a canvas (that’s `/onboarding`)
- Calling this a demo, exhibit, or walkthrough beat
- Freezing “five stages” if the tree shows a different shape
- Omitting Sentinel, Docker, CI evaluate-and-run, Aikido PR Checks, or Kody
- Treating Kody as a `pipeline.ts` stage or as merge approval
- Inventing seams or env values
- Dumping full manifest JSON
- Writing secrets from `.env`
- Naming specific `MOD-*` ticket ids unless the user asked for a live seam
