import "dotenv/config";
import { assertLocalDockerReady } from "./lib/dockerPreflight";
import { claimNextReadyTicket, handlePipelineFailure } from "./lib/linear";
import { resolvePipelineFromStage } from "./lib/manifest";
import { runPipeline } from "./pipeline";

const POLL_INTERVAL_MS = 5000;
let pipelineBusy = false;

/**
 * Production demo entry: local Docker for parity + Linear Ready polling.
 *
 * One-time (leave running during the demo):
 *   bash scripts/local-demo-start.sh
 *   npx tsx orchestrator/listener.ts
 *
 * Disable Cursor Automation — cloud DinD is not used for parity in this repo.
 */
async function poll(): Promise<void> {
  if (pipelineBusy) {
    return;
  }
  pipelineBusy = true;

  try {
    const claimed = await claimNextReadyTicket();
    if (!claimed) {
      return;
    }

    const fromStage = resolvePipelineFromStage(claimed.ticketId);
    console.log(
      `Starting pipeline for ticket ${claimed.ticketId}` +
        (fromStage > 1 ? " (resuming from stage 2 — cached manifest)" : "")
    );
    try {
      await runPipeline(claimed.ticketId, claimed.description, fromStage);
    } catch (err) {
      await handlePipelineFailure(claimed.ticketId, err);
    }
  } finally {
    pipelineBusy = false;
  }
}

try {
  assertLocalDockerReady();
} catch (err) {
  console.error((err as Error).message);
  process.exit(1);
}

console.log(
  "Listener started — polling Linear for Ready tickets every 5s (one pipeline at a time)"
);
console.log("Parity stack OK · disable Cursor Automation for this repo");

setInterval(() => {
  void poll();
}, POLL_INTERVAL_MS);

void poll();
