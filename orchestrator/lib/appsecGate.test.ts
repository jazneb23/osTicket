import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { appsecGateLabel, buildAppsecPrComment } from "./appsecGate";
import type { SentinelReport } from "./sentinel";

const clean: SentinelReport = { blocked: false, secrets: [], sast: [] };
const sast: SentinelReport = {
  blocked: false,
  secrets: [],
  sast: [{ kind: "sast", title: "Use of eval", severity: "high" }],
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
    assert.match(body, /Use of eval/);
    assert.match(body, /would block merge/i);
    assert.match(body, /critical or high/i);
  });
});
