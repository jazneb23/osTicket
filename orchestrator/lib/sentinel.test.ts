import assert from "node:assert/strict";
import {
  mkdirSync,
  mkdtempSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { chdir } from "node:process";
import { after, before, describe, it } from "node:test";
import {
  evaluateSentinel,
  parseAikidoScanResult,
  runSentinel,
  type AikidoFinding,
} from "./sentinel";

describe("parseAikidoScanResult", () => {
  it("reads issues from a findings array and classifies secrets vs SAST", () => {
    const findings = parseAikidoScanResult({
      findings: [
        {
          title: "Hardcoded OpenSSH private key",
          severity: "critical",
          type: "leaked_secret",
          file: "include/Services/TeamEnabledChecker.php",
          startLine: 18,
        },
        {
          title: "Use of eval",
          severity: "high",
          type: "sast",
          file: "include/Services/TopicActiveChecker.php",
          startLine: 22,
        },
      ],
    });

    assert.equal(findings.length, 2);
    assert.equal(findings[0].kind, "secret");
    assert.equal(findings[1].kind, "sast");
    assert.equal(findings[1].severity, "high");
  });

  it("reads the live Aikido MCP issue_* payload", () => {
    const findings = parseAikidoScanResult({
      issues: [
        {
          issue_title: "Unsafe eval usage can lead to remote code execution",
          issue_description:
            "Using eval on expressions based on user input can execute arbitrary code.",
          issue_remediation:
            "Avoid using eval if possible. Alternatively, use an allowlist for commands fed into the eval.",
          issue_severity: 89,
          issue_file: "include/Services/TopicActiveChecker.php",
          issue_start_line: 59,
          issue_snippet: "        return eval($payload);",
          issue_rule_id: "AIK_eval-use",
        },
      ],
    });

    assert.equal(findings.length, 1);
    assert.equal(findings[0].kind, "sast");
    assert.equal(findings[0].severity, "high");
    assert.equal(
      findings[0].title,
      "Unsafe eval usage can lead to remote code execution"
    );
    assert.equal(findings[0].file, "include/Services/TopicActiveChecker.php");
    assert.equal(findings[0].line, 59);
    assert.equal(findings[0].rule, "AIK_eval-use");
    assert.match(findings[0].snippet ?? "", /eval\(\$payload\)/);
  });

  it("unwraps MCP text content that embeds JSON", () => {
    const findings = parseAikidoScanResult({
      content: [
        {
          type: "text",
          text: JSON.stringify({
            issues: [
              {
                title: "eval() detected",
                severity: "HIGH",
                rule: "php.lang.security.eval-detected",
                path: "include/Services/TopicActiveChecker.php",
              },
            ],
          }),
        },
      ],
    });

    assert.equal(findings.length, 1);
    assert.equal(findings[0].kind, "sast");
    assert.equal(findings[0].title, "eval() detected");
  });

  it("returns an empty list for empty or unknown payloads", () => {
    assert.deepEqual(parseAikidoScanResult(null), []);
    assert.deepEqual(parseAikidoScanResult({}), []);
    assert.deepEqual(parseAikidoScanResult({ content: [{ type: "text", text: "ok" }] }), []);
  });
});

describe("evaluateSentinel", () => {
  const secret: AikidoFinding = {
    kind: "secret",
    title: "OpenSSH private key",
    severity: "critical",
    file: "include/Services/TeamEnabledChecker.php",
  };
  const sast: AikidoFinding = {
    kind: "sast",
    title: "Use of eval",
    severity: "high",
    file: "include/Services/TopicActiveChecker.php",
  };

  it("blocks only when a secret is present", () => {
    const blocked = evaluateSentinel([secret, sast]);
    assert.equal(blocked.blocked, true);
    assert.equal(blocked.secrets.length, 1);
    assert.equal(blocked.sast.length, 1);
  });

  it("does not block on SAST-only findings", () => {
    const reported = evaluateSentinel([sast]);
    assert.equal(reported.blocked, false);
    assert.equal(reported.sast.length, 1);
    assert.equal(reported.secrets.length, 0);
  });

  it("passes when Aikido returns nothing", () => {
    const clean = evaluateSentinel([]);
    assert.equal(clean.blocked, false);
    assert.equal(clean.sast.length, 0);
  });

  it("treats a secret seed as blocking if Aikido missed it", () => {
    const fallback = evaluateSentinel(
      [],
      "secret",
      "include/Services/TeamEnabledChecker.php"
    );
    assert.equal(fallback.blocked, true);
    assert.equal(fallback.secrets.length, 1);
    assert.match(fallback.secrets[0].title, /seed/i);
    assert.equal(
      fallback.secrets[0].file,
      "include/Services/TeamEnabledChecker.php"
    );
    assert.match(fallback.secrets[0].snippet ?? "", /BEGIN OPENSSH PRIVATE KEY/);
  });

  it("surfaces a SAST seed when Aikido missed it, without blocking", () => {
    const fallback = evaluateSentinel(
      [],
      "sast",
      "include/Services/TopicActiveChecker.php"
    );
    assert.equal(fallback.blocked, false);
    assert.equal(fallback.sast.length, 1);
    assert.equal(fallback.sast[0].severity, "high");
    assert.equal(
      fallback.sast[0].file,
      "include/Services/TopicActiveChecker.php"
    );
    assert.match(fallback.sast[0].snippet ?? "", /eval\(\$payload\)/);
  });

  it("backfills thin Aikido SAST rows from the seed instead of leaving unknown", () => {
    const reported = evaluateSentinel(
      [{ kind: "sast", title: "Aikido finding", severity: "unknown" }],
      "sast",
      "include/Services/TopicActiveChecker.php"
    );
    assert.equal(reported.blocked, false);
    assert.equal(reported.sast[0].title, "Demo-seeded eval() SAST");
    assert.equal(reported.sast[0].severity, "high");
    assert.equal(
      reported.sast[0].file,
      "include/Services/TopicActiveChecker.php"
    );
    assert.match(reported.sast[0].snippet ?? "", /eval\(\$payload\)/);
  });

  it("keeps a real Aikido SAST title when the payload is already rich", () => {
    const reported = evaluateSentinel(
      [
        {
          kind: "sast",
          title: "Unsafe eval usage can lead to remote code execution",
          severity: "high",
          file: "include/Services/TopicActiveChecker.php",
          line: 59,
          snippet: "return eval($payload);",
        },
      ],
      "sast",
      "include/Services/TopicActiveChecker.php"
    );
    assert.equal(
      reported.sast[0].title,
      "Unsafe eval usage can lead to remote code execution"
    );
  });
});

describe("runSentinel", () => {
  const originalCwd = process.cwd();
  let dir: string;

  before(() => {
    dir = mkdtempSync(join(tmpdir(), "osticket-sentinel-run-"));
    chdir(dir);
  });

  after(() => {
    chdir(originalCwd);
    rmSync(dir, { recursive: true, force: true });
  });

  it("scans the extracted service and applies seed fallback for MOD-30", async () => {
    mkdirSync("include/Services", { recursive: true });
    writeFileSync(
      "include/Services/TeamEnabledChecker.php",
      "<?php\nclass TeamEnabledChecker {\n    public function isEnabled($flags) { return $flags; }\n}\n"
    );
    const report = await runSentinel(
      {
        ticketId: "MOD-30",
        entryPoint: "Team::isEnabled()",
        coreLogic: "flags",
        consumers: [],
        inputShape: "flags",
        outputShape: "bool",
        sideEffects: [],
        constraints: [],
        extractionTarget: "include/Services/TeamEnabledChecker.php",
      },
      async () => ({ findings: [] })
    );
    assert.equal(report.blocked, true);
    assert.equal(report.secrets.length, 1);
  });
});
