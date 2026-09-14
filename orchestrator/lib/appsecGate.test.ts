import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { appsecGateLabel, buildAppsecPrComment } from "./appsecGate";
import type { SentinelReport } from "./sentinel";

const clean: SentinelReport = { blocked: false, secrets: [], sast: [] };
const sast: SentinelReport = {
  blocked: false,
  secrets: [],
  sast: [
    {
      kind: "sast",
      title: "Unsafe eval usage can lead to remote code execution",
      severity: "high",
      file: "include/Services/TopicActiveChecker.php",
      line: 59,
      description:
        "Using eval on expressions based on user input can execute arbitrary code.",
      rule: "AIK_eval-use",
      snippet: "return eval($payload);",
    },
  ],
};

describe("appsec PR gate exhibit", () => {
  it("labels a clean Sentinel report as pass", () => {
    assert.equal(appsecGateLabel(clean), "appsec:gate-pass");
  });

  it("labels SAST findings as fail for the merge-gate exhibit", () => {
    assert.equal(appsecGateLabel(sast), "appsec:gate-fail");
  });

  it("comments the would-be merge policy on the PR", () => {
    const body = buildAppsecPrComment("MOD-31", sast);
    assert.match(body, /AppSec gate/);
    assert.match(body, /Unsafe eval usage/);
    assert.match(body, /TopicActiveChecker\.php:59/);
    assert.match(body, /AIK_eval-use/);
    assert.match(body, /eval\(\$payload\)/);
    assert.match(body, /would block merge/i);
    assert.match(body, /critical or high/i);
  });
});
