import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildSentinelFailedComment,
  buildSentinelSastComment,
} from "./linear";
import type { SentinelReport } from "./sentinel";

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
    assert.match(body, /does not halt/i);
    assert.match(body, /pull request/i);
  });
});
