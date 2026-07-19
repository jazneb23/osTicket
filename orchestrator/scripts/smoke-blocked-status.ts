/**
 * Smoke-test Blocked status transitions against Linear, then restore the ticket.
 *
 * Usage:
 *   npx tsx orchestrator/scripts/smoke-blocked-status.ts MOD-32
 *   npx tsx orchestrator/scripts/smoke-blocked-status.ts MOD-32 --local-only
 *
 * Requires LINEAR_API_KEY. Uses an E2E-discard ticket (default MOD-32) in Backlog.
 * Does not run the pipeline or mutate repo extraction artifacts.
 */
import "dotenv/config";
import {
  STATUS_BLOCKED,
  STATUS_IN_PROGRESS,
  findTicketsByStatus,
  getTicketState,
  updateTicketStatus,
} from "../lib/linear";
import { incrementAttempts, maxStageRetries, readAttempts, resetAttempts } from "../lib/attempts";

const READY_STATUS = "Ready";

async function main(): Promise<void> {
  const args = process.argv.slice(2);
  const ticketId = args.find((a) => a.startsWith("MOD-")) ?? "MOD-32";
  const localOnly = args.includes("--local-only");

  console.log("=== Local attempts/retry cap ===");
  resetAttempts(ticketId);
  console.assert(readAttempts(ticketId) === 0, "attempts start at 0");
  console.assert(incrementAttempts(ticketId) === 1, "increment 1");
  console.assert(maxStageRetries() === 3, "default cap 3");
  const capped = incrementAttempts(ticketId) > maxStageRetries();
  console.log(`attempt 2 capped=${capped} (expected false)`);
  resetAttempts(ticketId);
  console.log("Local checks passed");

  if (localOnly) {
    console.log("--local-only: skipping Linear mutations");
    return;
  }

  console.log(`\n=== Linear smoke on ${ticketId} ===`);
  const before = await getTicketState(ticketId);
  console.log(`Before: ${before ?? "unknown"}`);

  try {
    await updateTicketStatus(ticketId, STATUS_IN_PROGRESS);
    const inProgress = await getTicketState(ticketId);
    if (inProgress !== STATUS_IN_PROGRESS) {
      throw new Error(`Expected In Progress, got ${inProgress}`);
    }
    console.log("OK · moved to In Progress");

    await updateTicketStatus(ticketId, STATUS_BLOCKED);
    const blocked = await getTicketState(ticketId);
    if (blocked !== STATUS_BLOCKED) {
      throw new Error(`Expected Blocked, got ${blocked}`);
    }
    console.log("OK · moved to Blocked");

    const blocking = await findTicketsByStatus(STATUS_IN_PROGRESS, 10);
    if (blocking.some((t) => t.identifier === ticketId)) {
      throw new Error(`${ticketId} still listed as In Progress`);
    }
    console.log("OK · ticket no longer In Progress (queue would not stall on this ticket)");

    await updateTicketStatus(ticketId, READY_STATUS);
    const ready = await getTicketState(ticketId);
    if (ready !== READY_STATUS) {
      throw new Error(`Cleanup failed: expected Ready, got ${ready}`);
    }
    console.log("OK · restored to Ready (move to Backlog manually if you prefer)");
  } catch (err) {
    console.error("Smoke test failed:", err);
    try {
      await updateTicketStatus(ticketId, "Backlog");
      console.log(`Restored ${ticketId} to Backlog after failure`);
    } catch (restoreErr) {
      console.error(`Failed to restore ${ticketId}:`, restoreErr);
    }
    process.exit(1);
  }

  resetAttempts(ticketId);
  console.log("\nSmoke test passed · no pipeline comments or repo artifacts created");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
