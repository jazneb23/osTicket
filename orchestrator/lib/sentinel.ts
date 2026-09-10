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

function classifyFinding(row: Record<string, unknown>): AikidoFindingKind {
  const blob = [row.type, row.kind, row.rule, row.scanner, row.category, row.title]
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
    (typeof row.title === "string" && row.title) ||
    (typeof row.message === "string" && row.message) ||
    (typeof row.rule === "string" && row.rule) ||
    "Aikido finding";
  const severityRaw =
    (typeof row.severity === "string" && row.severity) ||
    (typeof row.level === "string" && row.level) ||
    "unknown";
  const file =
    (typeof row.file === "string" && row.file) ||
    (typeof row.path === "string" && row.path) ||
    (typeof row.relativeFilePath === "string" && row.relativeFilePath) ||
    undefined;
  const lineRaw = row.startLine ?? row.line ?? row.lineNumber;
  const line = typeof lineRaw === "number" ? lineRaw : undefined;
  return {
    kind: classifyFinding(row),
    title,
    severity: severityRaw.toLowerCase(),
    file,
    line,
  };
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

function seedFinding(kind: AppsecSeedKind): AikidoFinding {
  if (kind === "secret") {
    return {
      kind: "secret",
      title: "Demo-seeded OpenSSH private key",
      severity: "critical",
    };
  }
  return {
    kind: "sast",
    title: "Demo-seeded eval() SAST",
    severity: "high",
  };
}

export function evaluateSentinel(
  findings: AikidoFinding[],
  seedKind?: AppsecSeedKind | null
): SentinelReport {
  const secrets = findings.filter((finding) => finding.kind === "secret");
  const sast = findings.filter((finding) => finding.kind === "sast");
  if (seedKind === "secret" && secrets.length === 0) {
    secrets.push(seedFinding("secret"));
  }
  if (seedKind === "sast" && sast.length === 0) {
    sast.push(seedFinding("sast"));
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
  try {
    const raw = await scan(files);
    const report = evaluateSentinel(parseAikidoScanResult(raw), seedKind);
    return report;
  } catch (err) {
    const scanError = err instanceof Error ? err.message : String(err);
    const report = evaluateSentinel([], seedKind);
    report.scanError = scanError;
    return report;
  }
}
