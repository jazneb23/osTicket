import "dotenv/config";
import { claimNextReadyTicket } from "./lib/linear";
import { runPipeline } from "./pipeline";

const POLL_INTERVAL_MS = 5000;
let pipelineBusy = false;

async function poll(): Promise<void> {
  // Claim the lock before any await so overlapping setInterval ticks cannot
  // both pass the guard and interleave ensureStranglerBranch checkouts.
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

console.log(
  "Listener started — polling Linear for Ready tickets every 5s (one pipeline at a time)"
);
console.log(
  "Prefer Automation: npx tsx orchestrator/automationWorker.ts --max 1 (do not run both)"
);

setInterval(() => {
  void poll();
}, POLL_INTERVAL_MS);

void poll();
