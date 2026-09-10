import type { AikidoFinding, SentinelReport } from "./sentinel";
import type { ParityReport, SeamManifest } from "./types";
import { incrementAttempts, maxStageRetries } from "./attempts";
import { paritySummary, passedFixtureNames } from "./slack";

const LINEAR_GRAPHQL_URL = "https://api.linear.app/graphql";
const SLA_MODERNIZATION_PROJECT = "SLA Modernization";
const READY_STATUS = "Ready";

const RETRYABLE_STATUS = new Set([408, 429, 500, 502, 503, 520, 522, 524, 525]);
const MAX_RETRIES = 4;
const BASE_DELAY_MS = 1000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/** Modernize (MOD) team workflow states — verified via list_issue_statuses */
export const STATUS_IN_PROGRESS = "In Progress";
export const STATUS_IN_REVIEW = "In Review";
export const STATUS_BLOCKED = "Blocked";

export interface LinearTicket {
  title: string;
  description: string;
}

interface GraphQLResponse<T> {
  data?: T;
  errors?: Array<{ message: string }>;
}

function requireApiKey(): string {
  const apiKey = process.env.LINEAR_API_KEY;
  if (!apiKey) {
    throw new Error(
      "LINEAR_API_KEY is required (Settings → Account → Security & access in Linear)"
    );
  }
  return apiKey;
}

async function linearGraphQL<T>(
  query: string,
  variables?: Record<string, unknown>
): Promise<T> {
  let lastError: Error | undefined;

  for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
    try {
      const response = await fetch(LINEAR_GRAPHQL_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: requireApiKey(),
        },
        body: JSON.stringify({ query, variables }),
      });

      if (!response.ok) {
        const err = new Error(
          `Linear API error: ${response.status} ${response.statusText}`
        );
        if (RETRYABLE_STATUS.has(response.status) && attempt < MAX_RETRIES) {
          lastError = err;
          await sleep(BASE_DELAY_MS * 2 ** attempt);
          continue;
        }
        throw err;
      }

      const json = (await response.json()) as GraphQLResponse<T>;
      if (json.errors?.length) {
        throw new Error(json.errors.map((error) => error.message).join("; "));
      }
      if (!json.data) {
        throw new Error("Linear API returned no data");
      }

      return json.data;
    } catch (err) {
      const error = err instanceof Error ? err : new Error(String(err));
      const retryable =
        error.message.includes("fetch failed") ||
        error.message.includes("ECONNRESET") ||
        error.message.includes("ETIMEDOUT");
      if (retryable && attempt < MAX_RETRIES) {
        lastError = error;
        await sleep(BASE_DELAY_MS * 2 ** attempt);
        continue;
      }
      throw error;
    }
  }

  throw lastError ?? new Error("Linear API request failed after retries");
}

export async function getLinearTicket(ticketId: string): Promise<LinearTicket> {
  const data = await linearGraphQL<{
    issue: { title: string; description: string | null } | null;
  }>(
    `query GetIssue($id: String!) {
      issue(id: $id) {
        title
        description
      }
    }`,
    { id: ticketId }
  );

  if (!data.issue) {
    throw new Error(`Linear issue not found: ${ticketId}`);
  }

  return {
    title: data.issue.title,
    description: data.issue.description ?? "",
  };
}

type ProjectIssue = { identifier: string; createdAt: string };

/** Tickets in a workflow state for the demo project (unsorted API page). */
export async function findTicketsByStatus(
  status: string,
  first = 10
): Promise<ProjectIssue[]> {
  const data = await linearGraphQL<{
    issues: { nodes: ProjectIssue[] };
  }>(
    `query FindTicketsByStatus($project: String!, $state: String!, $first: Int!) {
      issues(
        filter: {
          project: { name: { eq: $project } }
          state: { name: { eq: $state } }
        }
        orderBy: createdAt
        first: $first
      ) {
        nodes {
          identifier
          createdAt
        }
      }
    }`,
    { project: SLA_MODERNIZATION_PROJECT, state: status, first }
  );

  // Linear returns newest-first for orderBy: createdAt — normalize to oldest-first.
  return [...data.issues.nodes].sort((a, b) =>
    a.createdAt.localeCompare(b.createdAt)
  );
}

export async function findReadyTicket(): Promise<string | null> {
  // Fetch a page then pick oldest (Linear orderBy createdAt is newest-first).
  const nodes = await findTicketsByStatus(READY_STATUS, 50);
  return nodes[0]?.identifier ?? null;
}

export async function getTicketState(ticketId: string): Promise<string | null> {
  const data = await linearGraphQL<{
    issue: { state: { name: string } | null } | null;
  }>(
    `query GetIssueState($id: String!) {
      issue(id: $id) {
        state {
          name
        }
      }
    }`,
    { id: ticketId }
  );

  return data.issue?.state?.name ?? null;
}

/**
 * Serial claim for local listener: skip if any ticket is In Progress,
 * otherwise move the oldest Ready ticket to In Progress and return it.
 * On a rare double-claim race, revert the newer claim back to Ready.
 */
export async function claimNextReadyTicket(): Promise<{
  ticketId: string;
  description: string;
} | null> {
  const inProgress = await findTicketsByStatus(STATUS_IN_PROGRESS, 10);
  if (inProgress.length > 0) {
    console.log(
      `Queue paused · ${inProgress.map((t) => t.identifier).join(", ")} still In Progress in Linear — move to Ready if no pipeline is running`
    );
    return null;
  }

  const ready = await findTicketsByStatus(READY_STATUS, 50);
  const ticketId = ready[0]?.identifier;
  if (!ticketId) {
    console.log("No Ready tickets in queue");
    return null;
  }

  await updateTicketStatus(ticketId, STATUS_IN_PROGRESS);

  const afterClaim = await findTicketsByStatus(STATUS_IN_PROGRESS, 10);
  if (afterClaim.length > 1) {
    const winner = [...afterClaim].sort((a, b) =>
      a.createdAt.localeCompare(b.createdAt)
    )[0];
    if (winner && winner.identifier !== ticketId) {
      console.log(
        `Claim race · reverting ${ticketId} to Ready (winner ${winner.identifier})`
      );
      await updateTicketStatus(ticketId, READY_STATUS);
      return null;
    }
  }

  const state = await getTicketState(ticketId);
  if (state !== STATUS_IN_PROGRESS) {
    console.log(
      `Claim aborted · ${ticketId} is "${state ?? "unknown"}", expected "${STATUS_IN_PROGRESS}"`
    );
    return null;
  }

  const ticket = await getLinearTicket(ticketId);
  return { ticketId, description: ticket.description };
}

export async function updateTicketStatus(ticketId: string, status: string): Promise<void> {
  const issueData = await linearGraphQL<{
    issue: {
      team: {
        states: { nodes: Array<{ id: string; name: string }> };
      };
    } | null;
  }>(
    `query IssueStates($id: String!) {
      issue(id: $id) {
        team {
          states {
            nodes {
              id
              name
            }
          }
        }
      }
    }`,
    { id: ticketId }
  );

  if (!issueData.issue) {
    throw new Error(`Linear issue not found: ${ticketId}`);
  }

  const state = issueData.issue.team.states.nodes.find((node) => node.name === status);
  if (!state) {
    throw new Error(`Workflow state not found for ${ticketId}: ${status}`);
  }

  const result = await linearGraphQL<{
    issueUpdate: { success: boolean };
  }>(
    `mutation UpdateIssueState($id: String!, $stateId: String!) {
      issueUpdate(id: $id, input: { stateId: $stateId }) {
        success
      }
    }`,
    { id: ticketId, stateId: state.id }
  );

  if (!result.issueUpdate.success) {
    throw new Error(`Failed to update ${ticketId} to ${status}`);
  }
}

export function buildInReviewComment(
  manifest: SeamManifest,
  report: ParityReport,
  prUrl: string
): string {
  const fixtureList = passedFixtureNames(manifest.ticketId, report)
    .map((name) => `- ${name}`)
    .join("\n");
  const prLine = prUrl
    ? `[View pull request](${prUrl})`
    : "_PR URL not returned by agent_";

  return [
    "## Pipeline complete",
    "",
    "The strangler extraction pipeline finished successfully.",
    "",
    "### Completed stages",
    "",
    `- **Cartography** — Seam mapped at \`${manifest.entryPoint}\``,
    "- **Fixture generation** — Parity inputs proposed from manifest branches",
    `- **Extraction** — \`${manifest.extractionTarget ?? "(see PR)"}\` created`,
    `- **Strangler** — \`${manifest.facadeFile ?? "(see PR)"}\` patched to delegate`,
    "- **Sentinel** — Inline Aikido scan (secrets halt; SAST reported)",
    `- **Verification** — ${paritySummary(report)}`,
    "- **Pull request** — Opened for review",
    "",
    "### Parity verification",
    "",
    fixtureList,
    "",
    "### Pull request",
    "",
    prLine,
    "",
    "Kodus (Kody) reviews this PR automatically. Auto-approve is off — a human is the only approver.",
  ].join("\n");
}

/** Comment body when a pipeline stage throws — ticket returns to Ready or moves to Blocked. */
export function buildPipelineFailedComment(
  ticketId: string,
  error: unknown,
  attempt: number,
  capped: boolean
): string {
  const message =
    error instanceof Error ? error.message : String(error ?? "Unknown error");
  const maxRetries = maxStageRetries();
  const statusLine = capped
    ? `Ticket moved to **Blocked** after **${attempt}** consecutive stage failure(s) (limit: ${maxRetries}). Fix the issue, then move it back to **Ready** to retry.`
    : `Ticket moved back to **Ready** for retry (attempt **${attempt}/${maxRetries}**).`;

  return [
    "## Pipeline failed",
    "",
    `Run halted for **${ticketId}** before parity / PR.`,
    "",
    "```",
    message,
    "```",
    "",
    statusLine,
    "",
    "Check [cursor.com/agents](https://cursor.com/agents) for nested cloud agent logs if a stage failed there.",
  ].join("\n");
}

/** Comment body when publish/PR fails after parity passed — move to Blocked. */
export function buildPostParityFailedComment(
  ticketId: string,
  error: unknown
): string {
  const message =
    error instanceof Error ? error.message : String(error ?? "Unknown error");
  return [
    "## Publish / PR step failed",
    "",
    `Parity passed for **${ticketId}**, but publish or PR creation failed.`,
    "",
    "```",
    message,
    "```",
    "",
    "Ticket moved to **Blocked** — extraction work is preserved locally.",
    "",
    "Fix git auth or network, then move the ticket back to **Ready** and resume:",
    "",
    `\`npx tsx orchestrator/pipeline.ts ${ticketId} --from-stage 2\``,
    "",
    "(Manifest must still exist under `orchestrator/.state/`.)",
  ].join("\n");
}

function formatSentinelFindings(findings: AikidoFinding[]): string {
  if (findings.length === 0) {
    return "_No findings._";
  }
  return findings
    .map((finding) => {
      const where = [finding.file, finding.line != null ? `:${finding.line}` : ""]
        .join("")
        .trim();
      const loc = where ? ` \`${where}\`` : "";
      return `- **${finding.severity}** ${finding.title}${loc}`;
    })
    .join("\n");
}

/** Comment body when Sentinel halts on a secret — Blocked, no Ready retry. */
export function buildSentinelFailedComment(
  ticketId: string,
  report: SentinelReport
): string {
  return [
    "## Sentinel gate failed",
    "",
    `Inline Aikido scan halted **${ticketId}** before the verifier. No pull request opened.`,
    "",
    "A leaked secret is stop-the-line. Ticket moved to **Blocked** (no automatic retry).",
    "",
    "### Secrets",
    "",
    formatSentinelFindings(report.secrets),
    report.scanError
      ? `\n_Aikido MCP error (seed fallback applied):_ \`${report.scanError}\`\n`
      : "",
  ]
    .filter((section) => section !== "")
    .join("\n");
}

/** Visibility comment when Sentinel finds SAST but continues to verifier. */
export function buildSentinelSastComment(
  ticketId: string,
  report: SentinelReport
): string {
  return [
    "## Sentinel AppSec",
    "",
    `Inline Aikido scan on **${ticketId}** found SAST findings. Sentinel does not halt on SAST — the pipeline continues so parity can still produce a pull request.`,
    "",
    "The Aikido PR check / `appsec:gate-fail` label is the merge-gate exhibit.",
    "",
    "### SAST",
    "",
    formatSentinelFindings(report.sast),
    report.scanError
      ? `\n_Aikido MCP error (seed fallback applied):_ \`${report.scanError}\`\n`
      : "",
  ]
    .filter((section) => section !== "")
    .join("\n");
}

/** Sentinel secret halt: comment and Blocked. Never re-queue to Ready. */
export async function handleSentinelFailure(
  ticketId: string,
  report: SentinelReport
): Promise<void> {
  console.error("=".repeat(72));
  console.error(`SENTINEL GATE FAILED for ${ticketId} — pipeline halted, no PR opened`);
  console.error(`Ticket moving to "${STATUS_BLOCKED}" (no retry)`);
  for (const finding of report.secrets) {
    console.error(`  secret: ${finding.title}`);
  }
  console.error("=".repeat(72));
  try {
    await addIssueComment(ticketId, buildSentinelFailedComment(ticketId, report));
    console.error(`Posted Sentinel-failure comment on ${ticketId}`);
  } catch (err) {
    console.error(`Failed to comment Sentinel failure on Linear: ${err}`);
  }
  try {
    await updateTicketStatus(ticketId, STATUS_BLOCKED);
    console.error(`${ticketId} moved to ${STATUS_BLOCKED}`);
  } catch (err) {
    console.error(`Failed to move ${ticketId} to ${STATUS_BLOCKED}: ${err}`);
  }
}

/** Publish/PR failure after parity: comment and move to Blocked (no full restart). */
export async function handlePostParityFailure(
  ticketId: string,
  error: unknown
): Promise<void> {
  const message =
    error instanceof Error ? error.message : String(error ?? "Unknown error");
  console.error(`Post-parity step failed for ${ticketId}: ${message}`);
  try {
    await addIssueComment(ticketId, buildPostParityFailedComment(ticketId, error));
    console.error(`Posted post-parity failure comment on ${ticketId}`);
  } catch (commentErr) {
    console.error(`Failed to comment post-parity failure on Linear: ${commentErr}`);
  }
  try {
    await updateTicketStatus(ticketId, STATUS_BLOCKED);
    console.error(`${ticketId} moved to ${STATUS_BLOCKED}`);
  } catch (statusErr) {
    console.error(`Failed to move ${ticketId} to ${STATUS_BLOCKED}: ${statusErr}`);
  }
}

/** Agent/stage failure: comment on Linear and re-queue or block the ticket. */
export async function handlePipelineFailure(
  ticketId: string,
  error: unknown
): Promise<void> {
  const message =
    error instanceof Error ? error.message : String(error ?? "Unknown error");
  const attempt = incrementAttempts(ticketId);
  const capped = attempt > maxStageRetries();
  const nextStatus = capped ? STATUS_BLOCKED : READY_STATUS;

  console.error(`Pipeline failed for ${ticketId}: ${message}`);
  try {
    await addIssueComment(
      ticketId,
      buildPipelineFailedComment(ticketId, error, attempt, capped)
    );
    console.error(`Posted pipeline-failure comment on ${ticketId}`);
  } catch (commentErr) {
    console.error(`Failed to comment pipeline failure on Linear: ${commentErr}`);
  }
  try {
    await updateTicketStatus(ticketId, nextStatus);
    console.error(
      capped
        ? `${ticketId} moved to ${STATUS_BLOCKED} after ${attempt} stage failure(s)`
        : `${ticketId} moved back to ${READY_STATUS} for retry (attempt ${attempt}/${maxStageRetries()})`
    );
  } catch (statusErr) {
    console.error(`Failed to update ${ticketId} to ${nextStatus}: ${statusErr}`);
  }
}

/**
 * Field-level diff between two JSON-object-shaped strings (the fixture
 * harness contract). Falls back to raw expected/actual display when either
 * side isn't a parseable JSON object, so this stays safe for any ticket's
 * harness output shape (plain strings included).
 */
function describeMismatchDiff(expected: string | null, actual: string): string {
  try {
    const expectedObj = expected === null ? null : JSON.parse(expected);
    const actualObj = JSON.parse(actual);
    if (
      expectedObj &&
      typeof expectedObj === "object" &&
      typeof actualObj === "object" &&
      actualObj !== null
    ) {
      const keys = new Set([...Object.keys(expectedObj), ...Object.keys(actualObj)]);
      const diffs: string[] = [];
      for (const key of keys) {
        const e = (expectedObj as Record<string, unknown>)[key];
        const a = (actualObj as Record<string, unknown>)[key];
        if (JSON.stringify(e) !== JSON.stringify(a)) {
          diffs.push(`\`${key}\`: expected \`${JSON.stringify(e)}\`, got \`${JSON.stringify(a)}\``);
        }
      }
      if (diffs.length > 0) {
        return diffs.join("; ");
      }
    }
  } catch {
    // Not JSON objects on both sides — fall through to raw display below.
  }
  return `expected \`${expected}\`, got \`${actual}\``;
}

/** Comment body when the parity gate fails — no PR, ticket moves to Blocked. */
export function buildParityFailedComment(
  ticketId: string,
  report: ParityReport,
  manifest?: SeamManifest
): string {
  const mismatches = report.mismatches
    .map((m) => `- \`${m.name}\` — ${describeMismatchDiff(m.expected, m.actual)}`)
    .join("\n");

  const constraintsSection =
    manifest && manifest.constraints.length > 0
      ? [
          "",
          "### Seam constraints (from investigation)",
          "",
          "One of these is almost certainly what the extraction violates — check the field(s) named above against these:",
          "",
          manifest.constraints.map((c) => `- ${c}`).join("\n"),
        ].join("\n")
      : "";

  const suggestedFixSection = manifest
    ? [
        "",
        "### Suggested fix",
        "",
        `1. Compare \`${manifest.extractionTarget ?? "the extracted service"}\` against the constraint(s) above for the mismatched field(s).`,
        `2. Patch the service (or re-run the extractor) so it honors the violated constraint, without touching ${manifest.facadeFile ?? "the facade file"}'s signature or the fixtures.`,
        "3. Re-verify locally, then move the ticket back to **Ready** once it's fixed:",
        "",
        "```",
        `npx tsx orchestrator/capture-and-verify.ts ${ticketId}`,
        "```",
      ].join("\n")
    : "";

  return [
    "## Parity gate failed",
    "",
    `Pipeline halted for **${ticketId}**. No pull request opened.`,
    "",
    `Result: **${report.passed}/${report.totalCases}** passed, **${report.failed}** failed.`,
    "",
    "Ticket moved to **Blocked** — fix the extraction or fixtures, then move it back to **Ready** to retry.",
    "",
    "### Mismatches",
    "",
    mismatches || "_No mismatch details available._",
    constraintsSection,
    suggestedFixSection,
  ]
    .filter((section) => section !== "")
    .join("\n");
}

export async function addIssueComment(ticketId: string, body: string): Promise<void> {
  const result = await linearGraphQL<{
    commentCreate: { success: boolean };
  }>(
    `mutation CreateComment($input: CommentCreateInput!) {
      commentCreate(input: $input) {
        success
      }
    }`,
    { input: { issueId: ticketId, body } }
  );

  if (!result.commentCreate.success) {
    throw new Error(`Failed to add comment on ${ticketId}`);
  }
}
