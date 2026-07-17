import "dotenv/config";
import { claimNextReadyTicket } from "./lib/linear";
import { runPipeline } from "./pipeline";

/**
 * Single-worker entry for Cursor Automation (Linear Ready status change).
 *
 * Claims at most one Ready ticket when nothing is In Progress, then runs the
 * strangler pipeline. Put the claim lock here — not in a long Automation prompt.
 *
 * Prompt:
 *   bash .cursor/start.sh
 *   npx tsx orchestrator/automationWorker.ts --max 1
 */
function parseMax(): number {
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i++) {
    if (args[i] === "--max" && args[i + 1]) {
      const n = parseInt(args[i + 1], 10);
      if (!Number.isFinite(n) || n < 1) {
        throw new Error(`Invalid --max ${args[i + 1]} (expected integer >= 1)`);
      }
      return n;
    }
  }
  return 1;
}

export async function runAutomationWorker(max = 1): Promise<void> {
  let started = 0;

  while (started < max) {
    const claimed = await claimNextReadyTicket();
    if (!claimed) {
      break;
    }

    started += 1;
    console.log(
      `Automation worker · claimed ${claimed.ticketId} (${started}/${max})`
    );
    await runPipeline(claimed.ticketId, claimed.description);
  }

  if (started === 0) {
    console.log("Automation worker · idle (nothing claimed)");
  } else {
    console.log(`Automation worker · finished ${started} ticket(s)`);
  }
}

const isMain = process.argv[1]?.includes("automationWorker") ?? false;

if (isMain) {
  const max = parseMax();
  runAutomationWorker(max).catch((err) => {
    console.error("Automation worker failed:", err);
    process.exit(1);
  });
}
