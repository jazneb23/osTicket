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
    const fallback = evaluateSentinel([], "secret");
    assert.equal(fallback.blocked, true);
    assert.equal(fallback.secrets.length, 1);
    assert.match(fallback.secrets[0].title, /seed/i);
  });

  it("surfaces a SAST seed when Aikido missed it, without blocking", () => {
    const fallback = evaluateSentinel([], "sast");
    assert.equal(fallback.blocked, false);
    assert.equal(fallback.sast.length, 1);
    assert.equal(fallback.sast[0].severity, "high");
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
