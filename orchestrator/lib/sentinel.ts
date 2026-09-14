import * as fs from "fs";
import { seedKindForTicket, type AppsecSeedKind } from "./appsecSeed";
import { requireExtractionTarget } from "./manifest";
import type { SeamManifest } from "./types";

export type AikidoFindingKind = "secret" | "sast";

export type AikidoFinding = {
  kind: AikidoFindingKind;
  title: string;
  severity: string;
  file?: string;
  line?: number;
  description?: string;
  rule?: string;
  snippet?: string;
  remediation?: string;
};

export type SentinelReport = {
  blocked: boolean;
  secrets: AikidoFinding[];
  sast: AikidoFinding[];
  scanError?: string;
};

export type AikidoScanFile = {
  relativeFilePath: string;
  content: string;
};

export type AikidoScanFn = (files: AikidoScanFile[]) => Promise<unknown>;

function asRecord(value: unknown): Record<string, unknown> | null {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return null;
}

function parseJsonText(text: string): unknown {
  const trimmed = text.trim();
  const start = trimmed.search(/[\[{]/);
  if (start === -1) {
    return null;
  }
  try {
    return JSON.parse(trimmed.slice(start));
  } catch {
    return null;
  }
}

function unwrapMcpPayload(raw: unknown): unknown {
  const record = asRecord(raw);
  if (!record) {
    return raw;
  }
  const content = record.content;
  if (Array.isArray(content)) {
    for (const item of content) {
      const row = asRecord(item);
      if (row && row.type === "text" && typeof row.text === "string") {
        const parsed = parseJsonText(row.text);
        if (parsed != null) {
          return parsed;
        }
      }
    }
  }
  return raw;
}

function firstString(
  row: Record<string, unknown>,
  keys: string[]
): string | undefined {
  for (const key of keys) {
    const value = row[key];
    if (typeof value === "string" && value.trim()) {
      return value.trim();
    }
  }
  return undefined;
}

function firstNumber(
  row: Record<string, unknown>,
  keys: string[]
): number | undefined {
  for (const key of keys) {
    const value = row[key];
    if (typeof value === "number" && Number.isFinite(value)) {
      return value;
    }
  }
  return undefined;
}

function severityFromScore(score: number): string {
  if (score >= 90) return "critical";
  if (score >= 70) return "high";
  if (score >= 40) return "medium";
  return "low";
}

function classifyFinding(row: Record<string, unknown>): AikidoFindingKind {
  const blob = [
    row.type,
    row.kind,
    row.issue_type,
    row.rule,
    row.issue_rule_id,
    row.scanner,
    row.category,
    row.title,
    row.issue_title,
  ]
    .filter((part) => typeof part === "string")
    .join(" ")
    .toLowerCase();
  if (/(leaked[_\s-]?secret|secret|credential|private[_\s-]?key)/.test(blob)) {
    return "secret";
  }
  return "sast";
}

function normalizeFinding(value: unknown): AikidoFinding | null {
  const row = asRecord(value);
  if (!row) {
    return null;
  }
  const title =
    firstString(row, ["title", "issue_title", "message", "rule", "issue_rule_id"]) ||
    "Aikido finding";
  const severityLabel = firstString(row, [
    "severity",
    "level",
    "issue_severity_label",
  ]);
  const severityScore = firstNumber(row, [
    "issue_severity",
    "severity_score",
    "severity",
  ]);
  const severity = (
    severityLabel ||
    (severityScore != null ? severityFromScore(severityScore) : "unknown")
  ).toLowerCase();
  const file = firstString(row, [
    "file",
    "path",
    "relativeFilePath",
    "issue_file",
  ]);
  const line = firstNumber(row, [
    "startLine",
    "line",
    "lineNumber",
    "issue_start_line",
  ]);
  return {
    kind: classifyFinding(row),
    title,
    severity,
    file,
    line,
    description: firstString(row, ["description", "issue_description"]),
    rule: firstString(row, ["rule", "issue_rule_id"]),
    snippet: firstString(row, ["snippet", "issue_snippet"]),
    remediation: firstString(row, ["remediation", "issue_remediation"]),
  };
}

/** Markdown used on Linear and the GitHub AppSec-gate PR comment. */
export function formatAikidoFindingMarkdown(finding: AikidoFinding): string {
  const where = [finding.file, finding.line != null ? `:${finding.line}` : ""]
    .join("")
    .trim();
  const loc = where ? ` \`${where}\`` : "";
  const rule = finding.rule ? ` \`${finding.rule}\`` : "";
  const lines = [
    `- **${finding.severity}** ${finding.kind}: ${finding.title}${loc}${rule}`,
  ];
  if (finding.description) {
    lines.push(`  ${finding.description}`);
  }
  if (finding.snippet) {
    lines.push("  ```", `  ${finding.snippet}`, "  ```");
  }
  if (finding.remediation) {
    lines.push(`  _Remediation:_ ${finding.remediation}`);
  }
  return lines.join("\n");
}

function collectFindingRows(payload: unknown): unknown[] {
  if (Array.isArray(payload)) {
    return payload;
  }
  const record = asRecord(payload);
  if (!record) {
    return [];
  }
  for (const key of ["findings", "issues", "results", "vulnerabilities"]) {
    const value = record[key];
    if (Array.isArray(value)) {
      return value;
    }
  }
  const nested: unknown[] = [];
  for (const key of ["sast", "secrets", "secret", "leaked_secret"]) {
    const value = record[key];
    if (Array.isArray(value)) {
      nested.push(...value);
    }
  }
  return nested;
}

export function parseAikidoScanResult(raw: unknown): AikidoFinding[] {
  const payload = unwrapMcpPayload(raw);
  return collectFindingRows(payload)
    .map(normalizeFinding)
    .filter((finding): finding is AikidoFinding => finding != null);
}

function seedFinding(kind: AppsecSeedKind, file?: string): AikidoFinding {
  if (kind === "secret") {
    return {
      kind: "secret",
      title: "Demo-seeded OpenSSH private key",
      severity: "critical",
      file,
      description:
        "Hardcoded OpenSSH private-key material planted after Strangler so Sentinel can halt before a pull request is opened.",
      snippet: "-----BEGIN OPENSSH PRIVATE KEY-----",
      rule: "leaked_secret",
    };
  }
  return {
    kind: "sast",
    title: "Demo-seeded eval() SAST",
    severity: "high",
    file,
    description:
      "Using eval on expressions based on user input can execute arbitrary code.",
    snippet: "return eval($payload);",
    rule: "AIK_eval-use",
    remediation:
      "Avoid using eval if possible. Alternatively, use an allowlist for commands fed into the eval.",
  };
}

function isThinFinding(finding: AikidoFinding): boolean {
  return (
    finding.title === "Aikido finding" ||
    finding.severity === "unknown" ||
    !finding.file ||
    !finding.snippet
  );
}

function backfillFromSeed(finding: AikidoFinding, seed: AikidoFinding): AikidoFinding {
  return {
    ...finding,
    title: finding.title === "Aikido finding" ? seed.title : finding.title,
    severity: finding.severity === "unknown" ? seed.severity : finding.severity,
    file: finding.file || seed.file,
    line: finding.line ?? seed.line,
    description: finding.description || seed.description,
    rule: finding.rule || seed.rule,
    snippet: finding.snippet || seed.snippet,
    remediation: finding.remediation || seed.remediation,
  };
}

export function evaluateSentinel(
  findings: AikidoFinding[],
  seedKind?: AppsecSeedKind | null,
  extractionTarget?: string
): SentinelReport {
  let secrets = findings.filter((finding) => finding.kind === "secret");
  let sast = findings.filter((finding) => finding.kind === "sast");
  if (seedKind === "secret") {
    const seed = seedFinding("secret", extractionTarget);
    if (secrets.length === 0) {
      secrets = [seed];
    } else if (secrets.some(isThinFinding)) {
      secrets = secrets.map((finding) => backfillFromSeed(finding, seed));
    }
  }
  if (seedKind === "sast") {
    const seed = seedFinding("sast", extractionTarget);
    if (sast.length === 0) {
      sast = [seed];
    } else if (sast.some(isThinFinding)) {
      sast = sast.map((finding) => backfillFromSeed(finding, seed));
    }
  }
  return {
    blocked: secrets.length > 0,
    secrets,
    sast,
  };
}

function scanTargets(manifest: SeamManifest): AikidoScanFile[] {
  const files: AikidoScanFile[] = [];
  const extractionTarget = requireExtractionTarget(manifest);
  const paths = [extractionTarget, manifest.facadeFile].filter(
    (path): path is string => typeof path === "string" && path.length > 0
  );
  for (const relativeFilePath of paths) {
    if (!fs.existsSync(relativeFilePath)) {
      continue;
    }
    files.push({
      relativeFilePath,
      content: fs.readFileSync(relativeFilePath, "utf-8"),
    });
  }
  if (files.length === 0) {
    throw new Error(
      `Sentinel has nothing to scan for ${manifest.ticketId}: missing ${extractionTarget}`
    );
  }
  return files;
}

export async function runSentinel(
  manifest: SeamManifest,
  scan: AikidoScanFn
): Promise<SentinelReport> {
  const files = scanTargets(manifest);
  const seedKind = seedKindForTicket(manifest.ticketId);
  const extractionTarget = requireExtractionTarget(manifest);
  try {
    const raw = await scan(files);
    const report = evaluateSentinel(
      parseAikidoScanResult(raw),
      seedKind,
      extractionTarget
    );
    return report;
  } catch (err) {
    const scanError = err instanceof Error ? err.message : String(err);
    const report = evaluateSentinel([], seedKind, extractionTarget);
    report.scanError = scanError;
    return report;
  }
}
