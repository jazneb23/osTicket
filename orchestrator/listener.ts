import "dotenv/config";
import { assertLocalDockerReady } from "./lib/dockerPreflight";
import { claimNextReadyTicket } from "./lib/linear";
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

    console.log(`Starting pipeline for ticket ${claimed.ticketId}`);
    await runPipeline(claimed.ticketId, claimed.description);
  } catch (err) {
    console.error("Poll failed:", err);
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
