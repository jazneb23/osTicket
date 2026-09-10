# Demo overlay — strangler-fig migration pipeline

This is the small, deliberately separated **demo layer** on top of the host
app. Confirm stage order against `orchestrator/pipeline.ts` before briefing —
active seams and ticket ids drift as the demo is reset/replayed.

## Key types (`orchestrator/lib/types.ts`)

**SeamManifest** — cartographer output; drives later stages: `ticketId`,
`entryPoint`, `coreLogic`, `consumers`, `inputShape`, `outputShape`,
`sideEffects`, `constraints`, `facadeFile` (host-app PHP file to patch),
`extractionTarget` (new service under `include/Services/`), `harnessScript`
(under `legacy/harness/`), `harnessInputShape`.

**Fixture** — one parity case: prefer generic `input` + `expected`; legacy
SLA fields (`graceHours`, `start`, `scheduleId`) still supported for MOD-25.

**ParityReport** — verifier output: `passed` / `failed` / `totalCases`,
`mismatches[]`, **`gatePassed`**.

## Stage order

| # | Stage | Agent file | Runtime | Purpose |
|---|-------|------------|---------|---------|
| 1 | cartographer | `agents/cartographer.ts` | Cloud | Investigate legacy PHP; write seam manifest JSON (no implementation) |
| 2a | harnessBuilder | `agents/harnessBuilder.ts` | Local | Create/reuse `legacy/harness/<seam>_capture.php` |
| 2b | fixtureGenerator | `agents/fixtureGenerator.ts` | Cloud | Propose fixture cases under `orchestrator/fixtures/<ticket>/` |
| 2c | baselineCapture | `agents/baselineCapture.ts` | Local (harness) | Run harness; fill real `expected` values |
| 3 | extractor | `agents/extractor.ts` | Local | Create thin service at `extractionTarget` |
| 4 | strangler | `agents/strangler.ts` | Local | Smallest facade patch at `facadeFile` |
| 4b | sentinel | `lib/sentinel.ts` (pipeline hook) | Local | Aikido MCP scan of facade + extracted service. Secrets → Blocked (no retry). SAST → Linear comment, continue. Seeds MOD-30 secret / MOD-31 HIGH SAST. Skipped for MOD-27. |
| 5 | verifier | `agents/verifier.ts` | Local (harness) | Compare harness output to fixture expecteds → `ParityReport` |
| 6 | prAgent | `agents/prAgent.ts` | Local `gh` | Open PR **only if** `gatePassed`; label AppSec gate exhibit |

`fromStage` in `runPipeline(ticketId, criteria, fromStage)` skips earlier work
(e.g. `--from-stage 3` reuses harness/fixtures/baseline). Pinned regression
tickets (see below) force `fromStage = 5` regardless of the flag.

## Gate behavior (sacred)

After Strangler, Sentinel runs (except pinned regression tickets):

**Secret** (`report.blocked === true`):

- Halt; Linear **Blocked**; **no** verifier, **no** PR, **no** Ready retry
- Comment via `buildSentinelFailedComment`

**SAST only**:

- Linear visibility comment; continue to verifier

After verifier:

**Pass** (`report.gatePassed === true`):

1. Hands-off by default: publish artifacts (`gitPublish`) then open PR
   (set `PIPELINE_PAUSE_FOR_REVIEW=1` only for legacy demo pause **logs**)
2. `prAgent` opens PR
3. Slack `notifyPrOpened`
4. Linear comment + status → **In Review**

**Fail**:

- Halt pipeline; move ticket to **Blocked**
- **No** PR, **no** Slack success notify, **no** In Review
- Print mismatches (`expected` vs `actual`); Linear comment via `buildParityFailedComment`

**Stage throw** (before verifier):

- Retry via **Ready** up to `PIPELINE_MAX_STAGE_RETRIES` (default 3, env-configurable)
- After cap, move to **Blocked** via `handlePipelineFailure`
- Attempt counter resets when verifier runs successfully (parity pass or fail)

Never skip, weaken, or bypass the verifier. Never invent expecteds or edit
fixtures just to pass.

## What each stage must / must not do

- **Cartographer** — trace real call sites by reading files; emit one JSON
  object matching `SeamManifest`; set `facadeFile`, `extractionTarget`,
  `harnessScript`, `harnessInputShape`; **no** implementation code.
- **Harness builder** — bootstrap via `main.inc.php` (never isolated class
  includes); prefer reusing an existing harness if it covers the seam;
  scripts are tools, not product code.
- **Fixture generator + baseline capture** — fixtures need **real**
  `expected` values from running legacy code; baseline capture writes those
  values; humans/agents must not guess them.
- **Extractor** — thin service under `include/Services/`; lifted logic or
  forward delegation only — never call the facade entry point.
- **Strangler** — preserve method signature and facade-level side effects /
  orchestration; smallest possible patch; only files named in the manifest.
- **Sentinel** — pipeline-owned Aikido scan, not an optional agent MCP call.
  Halt only on secrets. Do not "fix" `DEMO-ONLY AppSec seed` blocks on MOD-30/31.
- **Verifier** — runs harness per fixture; builds `ParityReport`; sole
  authority for `gatePassed`.
- **PR agent** — runs only after gate pass; scoped to facade + extraction
  target from manifest.

## Active seams (verify in tree — ticket ids get reset/replayed)

| Ticket | Facade | Service | Harness | Notes |
|--------|--------|---------|---------|-------|
| MOD-25 | `include/class.sla.php` (`addGracePeriod`) | `SlaGracePeriodCalculator.php` | `legacy/harness/sla_capture.php` | Proven seam; harness path pinned in `manifest.ts` |
| MOD-27 | `include/class.ticket.php` (`isOverdue`/`markOverdue`/`clearOverdue`/`checkOverdue`) | `TicketOverdueService.php` | `legacy/harness/overdue_capture.php` | **Deliberately pinned parity-regression demo** — see below |

AppSec demo plants (applied after Strangler by `seedAppsecDemoFinding`; not on MOD-25/27/28): **MOD-30** secret (Sentinel Blocks, no PR), **MOD-31** HIGH SAST (PR + `appsec:gate-fail`). In a full Ready batch, oldest-first means MOD-25 runs first; MOD-28 is a normal passing E2E ticket.

MOD-27's manifest is baked into `orchestrator/lib/manifest.ts`
(`PINNED_MANIFESTS`), not just cached under gitignored `orchestrator/.state/`
— its extracted `checkOverdue()` ships with a real, documented bug so the
parity gate reliably fails and the ticket lands in `Blocked` for demo
purposes. Don't "fix" that bug via a fresh extractor run without
understanding this is intentional. New tickets should be **manifest-driven**,
not hardcoded like this.

## Entry points

```bash
# Prod: local listener (see .github/LOCAL_DEMO_SETUP.md)
bash scripts/local-demo-start.sh
npx tsx orchestrator/listener.ts

# Full pipeline for one ticket (manual / debug)
npx tsx orchestrator/pipeline.ts MOD-<id> --criteria "<acceptance text>" [--from-stage N]

# Capture baselines then verify one ticket
npx tsx orchestrator/capture-and-verify.ts [MOD-id]

# CI: verify every orchestrator/fixtures/MOD-* suite
npx tsx orchestrator/ci-parity-check.ts
```

## Listener flow

`orchestrator/listener.ts` is the **production demo entry**. It polls Linear
for Ready tickets (one at a time via `claimNextReadyTicket` + `pipelineBusy`):
claim next Ready ticket (Blocked tickets don't block the queue) → mark In
Progress → `runPipeline(ticketId, ticket.description)`. Requires local
Docker. Disable Cursor Automation.

## Manifest + state locations

| Path | Role |
|------|------|
| `orchestrator/lib/manifest.ts` `PINNED_MANIFESTS` | Baked-in manifest for demo-stable tickets (e.g. MOD-27) — survives `/cleanup` and fresh clones |
| `orchestrator/.state/MOD-*-manifest.json` | Runtime cache (gitignored); cartographer writes this; `/cleanup` deletes it so the next run recartographs |
| `orchestrator/manifests/MOD-*-manifest.json` | Copy of the seam manifest **on the `strangler/MOD-*` PR branch only**, so GitHub Actions can run parity. Never merge to `develop`. `/cleanup` removes it by deleting the branch |
| `orchestrator/fixtures/MOD-*/` | One JSON file per case |
| `orchestrator/fixtures/parity.json` | Aggregate parity artifact if present (generated; don't commit) |

`ci-parity-check.ts` discovers ticket ids from fixture directories and looks for a cartographer JSON in `.state/` or `orchestrator/manifests/`.

## Libraries to reuse (do not reinvent)

- `withLocalAgent` / `withCloudAgent`, streaming helpers — `lib/sdk.ts`
- `loadManifest`, `requireFacadeFile`, `requireExtractionTarget`,
  `requireHarnessScript`, `fixtureHarnessInput`, `isPinnedRegressionTicket` — `lib/manifest.ts`
- `runHarness` — `lib/harness.ts`
- `publishArtifactsForPr` — `lib/gitPublish.ts` (before nested cloud PR agent)
- Linear/Slack helpers — `lib/linear.ts`, `lib/slack.ts`
- Keep agents **thin**: prompts + I/O only
