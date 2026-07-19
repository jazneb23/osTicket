import * as fs from "fs";
import type { Fixture, SeamManifest } from "./types";

/** MOD-25 uses the existing hand-verified harness; do not require a new script. */
export const MOD_25_HARNESS_SCRIPT = "legacy/harness/sla_capture.php";
export const MOD_25_HARNESS_INPUT_SHAPE =
  "JSON object with: start (string, ISO 8601 datetime); hours (number, grace period hours — use 0 for guard cases); schedule_id (integer, BusinessHoursSchedule id — 1 for normal business-hours schedule, 5 for empty-schedule fallback testing)";

export function manifestPath(ticketId: string): string {
  return `orchestrator/.state/${ticketId}-manifest.json`;
}

/** Resume after a stage failure without re-running cartographer when manifest exists. */
export function resolvePipelineFromStage(ticketId: string): number {
  return fs.existsSync(manifestPath(ticketId)) ? 2 : 1;
}

export function loadManifest(ticketId: string): SeamManifest {
  const path = manifestPath(ticketId);
  if (!fs.existsSync(path)) {
    throw new Error(
      `No manifest found for ${ticketId} at ${path}. Run stage 1 (cartographer) first.`
    );
  }
  return applyHarnessDefaults(
    JSON.parse(fs.readFileSync(path, "utf-8")) as SeamManifest
  );
}

/** Pin known harness paths for tickets with existing proven fixtures. */
export function applyHarnessDefaults(manifest: SeamManifest): SeamManifest {
  if (manifest.ticketId === "MOD-25") {
    return {
      ...manifest,
      harnessScript: MOD_25_HARNESS_SCRIPT,
      harnessInputShape: MOD_25_HARNESS_INPUT_SHAPE,
    };
  }
  return manifest;
}

/**
 * Demo-only pinned parity regression. MOD-27's extraction target
 * (include/Services/TicketOverdueService.php) intentionally ships with a
 * real, documented bug in checkOverdue() (see the "KNOWN DEMO REGRESSION"
 * comment in that file) so the parity gate reliably fails and the ticket
 * lands in Blocked with a diagnosable comment.
 *
 * Extractor/strangler are LLM-driven and would likely "fix" the bug on a
 * fresh run, which would make the failure non-deterministic. Tickets in
 * this set skip straight from the (cached) manifest to the verifier —
 * stages 2-4 are never re-run against already-pinned artifacts.
 *
 * The manifest itself is baked in below (not just cached under the
 * gitignored orchestrator/.state/) so this stays reproducible on a fresh
 * clone, in CI, or after orchestrator/.state/ is wiped (e.g. /cleanup) —
 * no manual re-run of cartographer is ever required for this ticket.
 */
export const PINNED_MANIFESTS: Record<string, SeamManifest> = {
  "MOD-27": {
    ticketId: "MOD-27",
    entryPoint:
      "Ticket::isOverdue(), Ticket::markOverdue($whine=true), Ticket::clearOverdue($save=true), and static Ticket::checkOverdue() in include/class.ticket.php",
    coreLogic:
      "markOverdue($whine): guard on isOpen() -> false if closed; idempotent no-op (return true) if already isOverdue(); else set isoverdue=1, save(), then logEvent('overdue') and onOverdue($whine). clearOverdue($save): if isOverdue() set isoverdue=0 (never annuls the previously logged overdue event); null duedate only if getDueDate() is set and past (Misc::db2gmtime <= Misc::gmtime()); null est_duedate only if getSLADueDate() is set and past; honor $save (persist via save() or return true without writing). checkOverdue(): static batch query over open, not-yet-overdue (isoverdue=0) tickets matching Q::any(Q::all(duedate is null AND est_duedate is not null AND est_duedate < NOW()), Q::all(duedate is not null AND duedate < NOW())), limit 100, then calls markOverdue() on each match.",
    consumers: [
      "scp/tickets.php — staff 'mark overdue' action",
      "include/staff/ticket-view.inc.php — overdue badge/menu rendering",
      "include/staff/templates/ticket-preview.tmpl.php — overdue badge in preview",
      "include/class.cron.php — cron entry point calling Ticket::checkOverdue()",
      "include/class.ticket.php setState() dispatcher — 'overdue' maps to markOverdue(), 'notdue' maps to clearOverdue()",
      "include/class.ticket.php setStatus() closed-state branch — calls clearOverdue(false)",
      "include/class.ticket.php updateEstDueDate() — calls clearOverdue(false) then recomputes est_duedate",
    ],
    inputShape:
      "markOverdue(bool $whine=true); clearOverdue(bool $save=true); checkOverdue() takes no arguments (static, operates on the whole open-ticket table).",
    outputShape:
      "isOverdue(): bool. markOverdue(): bool (false if not open or save() failed, true otherwise, including the idempotent no-op case). clearOverdue(): bool (save() result, or true when $save=false). checkOverdue(): void — mutates matching Ticket rows via markOverdue().",
    sideEffects: [
      "DB write: ost_ticket.isoverdue",
      "DB write: ost_ticket.duedate",
      "DB write: ost_ticket.est_duedate",
      "logEvent('overdue') ticket-thread event on transition to overdue",
      "onOverdue($whine) — reads global $cfg and the ticket's SLA to conditionally send staff/dept alert emails",
      "checkOverdue() runs a batched ORM query (Q::any/Q::all) with limit(100) and calls markOverdue() per matched row — cron-triggered side effects at scale",
    ],
    constraints: [
      "markOverdue() only marks OPEN tickets; returns false immediately for closed/other-state tickets.",
      "markOverdue() is idempotent: if isOverdue() is already true, return true without re-saving, re-logging, or re-alerting.",
      "markOverdue() must save() before logEvent('overdue')/onOverdue($whine); if save() fails, return false and skip logging/alerting.",
      "clearOverdue() NEVER annuls a previously logged 'overdue' event — it only clears the isoverdue flag/dates going forward.",
      "clearOverdue() nulls duedate only when getDueDate() is set AND in the past (Misc::db2gmtime(duedate) <= Misc::gmtime()); same past-due gating for est_duedate via getSLADueDate().",
      "clearOverdue($save) must honor $save: true persists via save(); false mutates the in-memory instance and returns true WITHOUT writing to the DB.",
      "checkOverdue() batch selection: isoverdue=0 AND status__state='open' AND EITHER (duedate IS NULL AND est_duedate IS NOT NULL AND est_duedate < NOW()) OR (duedate IS NOT NULL AND duedate < NOW()). CRITICAL PRECEDENCE RULE: whenever duedate IS SET (not null), est_duedate is ignored entirely for batch selection purposes, even if est_duedate is also in the past and duedate is not. A ticket with a future duedate and a past est_duedate must NOT be selected.",
      "checkOverdue() limits the batch to 100 rows per invocation.",
    ],
    facadeFile: "include/class.ticket.php",
    extractionTarget: "include/Services/TicketOverdueService.php",
    harnessScript: "legacy/harness/overdue_capture.php",
    harnessInputShape:
      "JSON object with: action (string: 'isOverdue'|'markOverdue'|'clearOverdue'|'checkOverdue'); ticket_id (int); status_state (optional string: 'open'|'closed', seeded via setStatusId() before the call); isoverdue (optional 0|1); duedate (optional 'Y-m-d H:i:s' or null); est_duedate (optional 'Y-m-d H:i:s' or null); whine (optional bool, for markOverdue); save (optional bool, for clearOverdue).",
  },
};

export function isPinnedRegressionTicket(ticketId: string): boolean {
  return ticketId in PINNED_MANIFESTS;
}

/** Ensure a pinned ticket's manifest is on disk before cartographer's cache check runs. */
export function seedPinnedManifest(ticketId: string): void {
  const pinned = PINNED_MANIFESTS[ticketId];
  if (!pinned) {
    return;
  }
  fs.mkdirSync("orchestrator/.state", { recursive: true });
  fs.writeFileSync(manifestPath(ticketId), JSON.stringify(pinned, null, 2));
}

export function requireExtractionTarget(manifest: SeamManifest): string {
  if (!manifest.extractionTarget) {
    throw new Error(
      `Manifest for ${manifest.ticketId} is missing extractionTarget. Re-run cartographer.`
    );
  }
  return manifest.extractionTarget;
}

export function requireFacadeFile(manifest: SeamManifest): string {
  if (!manifest.facadeFile) {
    throw new Error(
      `Manifest for ${manifest.ticketId} is missing facadeFile. Re-run cartographer.`
    );
  }
  return manifest.facadeFile;
}

export function requireHarnessScript(manifest: SeamManifest): string {
  if (!manifest.harnessScript) {
    throw new Error(
      `Manifest for ${manifest.ticketId} is missing harnessScript. Re-run cartographer.`
    );
  }
  return manifest.harnessScript;
}

/** Build harness payload from a fixture (supports generic `input` or legacy SLA fields). */
export function fixtureHarnessInput(fixture: Fixture): Record<string, unknown> {
  if (fixture.input) {
    return fixture.input;
  }
  return {
    start: fixture.start,
    hours: fixture.graceHours,
    schedule_id: fixture.scheduleId,
  };
}
