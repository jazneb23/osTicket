import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildInReviewComment,
  buildSentinelFailedComment,
  buildSentinelSastComment,
} from "./linear";
import type { SentinelReport } from "./sentinel";
import type { ParityReport, SeamManifest } from "./types";

const secretReport: SentinelReport = {
  blocked: true,
  secrets: [
    {
      kind: "secret",
      title: "OpenSSH private key",
      severity: "critical",
      file: "include/Services/TeamEnabledChecker.php",
    },
  ],
  sast: [],
};

const sastReport: SentinelReport = {
  blocked: false,
  secrets: [],
  sast: [
    {
      kind: "sast",
      title: "Use of eval",
      severity: "high",
      file: "include/Services/TopicActiveChecker.php",
      line: 22,
    },
  ],
};

describe("Sentinel Linear comments", () => {
  it("builds a no-retry Blocked comment for a secret halt", () => {
    const body = buildSentinelFailedComment("MOD-30", secretReport);
    assert.match(body, /^## Sentinel gate failed/);
    assert.match(body, /MOD-30/);
    assert.match(body, /OpenSSH private key/);
    assert.match(body, /Blocked/);
    assert.doesNotMatch(body, /Ready/);
    assert.match(body, /no automatic retry/i);
  });

  it("builds a visibility comment for HIGH SAST that does not halt", () => {
    const body = buildSentinelSastComment("MOD-31", sastReport);
    assert.match(body, /^## Sentinel AppSec/);
    assert.match(body, /MOD-31/);
    assert.match(body, /Use of eval/);
    assert.match(body, /TopicActiveChecker\.php:22/);
    assert.match(body, /does not halt/i);
    assert.match(body, /pull request/i);
  });
});

const inReviewManifest: SeamManifest = {
  ticketId: "MOD-25",
  entryPoint: "SLA::addGracePeriod()",
  coreLogic: "grace hours",
  consumers: [],
  inputShape: "start",
  outputShape: "datetime",
  sideEffects: [],
  constraints: [],
  facadeFile: "include/class.sla.php",
  extractionTarget: "include/Services/SlaGracePeriodCalculator.php",
};

const passedParity: ParityReport = {
  totalCases: 6,
  passed: 6,
  failed: 0,
  mismatches: [],
  gatePassed: true,
};

describe("Pipeline complete Linear comment", () => {
  it("repeats SAST findings on the success comment so the AppSec exhibit is not buried", () => {
    const body = buildInReviewComment(
      inReviewManifest,
      passedParity,
      "https://github.com/jazneb23/osTicket/pull/125",
      sastReport
    );
    assert.match(body, /## Pipeline complete/);
    assert.match(body, /### AppSec/);
    assert.match(body, /appsec:gate-fail/);
    assert.match(body, /Use of eval/);
    assert.match(body, /TopicActiveChecker\.php:22/);
  });

  it("omits the AppSec section when Sentinel is clean", () => {
    const body = buildInReviewComment(
      inReviewManifest,
      passedParity,
      "https://github.com/jazneb23/osTicket/pull/121"
    );
    assert.doesNotMatch(body, /### AppSec/);
    assert.doesNotMatch(body, /appsec:gate-fail/);
  });
});
