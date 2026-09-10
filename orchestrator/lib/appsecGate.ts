import { execFileSync } from "child_process";
import type { SentinelReport } from "./sentinel";

export type AppsecGateLabel = "appsec:gate-fail" | "appsec:gate-pass";

export function appsecGateLabel(report: SentinelReport): AppsecGateLabel {
  return report.sast.length > 0 || report.secrets.length > 0
    ? "appsec:gate-fail"
    : "appsec:gate-pass";
}

function formatFindings(report: SentinelReport): string {
  const rows = [...report.secrets, ...report.sast];
  if (rows.length === 0) {
    return "_No inline Aikido findings._";
  }
  return rows
    .map((finding) => `- **${finding.severity}** ${finding.kind}: ${finding.title}`)
    .join("\n");
}

export function buildAppsecPrComment(ticketId: string, report: SentinelReport): string {
  const label = appsecGateLabel(report);
  const policy =
    label === "appsec:gate-fail"
      ? "In production, critical or high findings on this check would block merge. This demo never merges — the failing label is the gate exhibit."
      : "No blocking AppSec findings from the inline scan. In production, the Aikido PR check would still need to pass before merge.";

  return [
    "## AppSec gate",
    "",
    `Sentinel (inline Aikido) on **${ticketId}**. Label: \`${label}\`.`,
    "",
    formatFindings(report),
    "",
    policy,
  ].join("\n");
}

function ghRepoArgs(): string[] {
  const url = process.env.GITHUB_REPO_URL;
  if (!url) return [];
  const match = url.match(/github\.com[:/]([^/]+)\/([^/?.]+)/);
  if (!match) return [];
  return ["--repo", `${match[1]}/${match[2]}`];
}

function ensureLabel(name: AppsecGateLabel): void {
  const color = name === "appsec:gate-fail" ? "B60205" : "0E8A16";
  try {
    execFileSync(
      "gh",
      ["label", "create", name, ...ghRepoArgs(), "--color", color, "--description", "Demo AppSec merge-gate exhibit"],
      { stdio: "ignore" }
    );
  } catch {
    // Label already exists (or gh cannot create it — add-label will surface that).
  }
}

/** Label + comment the PR. Best-effort — must not fail the pipeline after the PR exists. */
export function applyAppsecGateExhibit(
  prUrl: string,
  ticketId: string,
  report: SentinelReport
): void {
  const label = appsecGateLabel(report);
  const body = buildAppsecPrComment(ticketId, report);
  ensureLabel(label);
  execFileSync(
    "gh",
    ["pr", "edit", prUrl, ...ghRepoArgs(), "--add-label", label],
    { stdio: ["ignore", "pipe", "pipe"] }
  );
  execFileSync(
    "gh",
    ["pr", "comment", prUrl, ...ghRepoArgs(), "--body", body],
    { stdio: ["ignore", "pipe", "pipe"] }
  );
}
