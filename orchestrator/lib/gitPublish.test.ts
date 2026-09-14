import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { buildPrBody, publishPathsForTicket } from "./gitPublish";
import type { SentinelReport } from "./sentinel";
import type { ParityReport, SeamManifest } from "./types";

describe("publishPathsForTicket", () => {
  it("includes the committed seam manifest so CI can find it on the PR branch", () => {
    const paths = publishPathsForTicket(
      "MOD-25",
      ["include/class.sla.php"],
      "legacy/harness/sla_capture.php"
    );

    assert.ok(
      paths.includes("orchestrator/manifests/MOD-25-manifest.json"),
      `expected committed manifest path, got: ${paths.join(", ")}`
    );
  });
});

const manifest: SeamManifest = {
  ticketId: "MOD-31",
  entryPoint: "Topic::isActive()",
  coreLogic: "FLAG_ACTIVE bit",
  consumers: ["Topic::isEnabled()"],
  inputShape: "flags",
  outputShape: "bool",
  sideEffects: [],
  constraints: [],
  facadeFile: "include/class.topic.php",
  extractionTarget: "include/Services/TopicActiveChecker.php",
};

const parity: ParityReport = {
  totalCases: 6,
  passed: 6,
  failed: 0,
  mismatches: [],
  gatePassed: true,
};

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
      snippet: "return eval($payload);",
      rule: "AIK_eval-use",
    },
  ],
};

describe("buildPrBody", () => {
  it("adds an AppSec gate section when Sentinel reported SAST", () => {
    const body = buildPrBody(manifest, parity, sast);
    assert.match(body, /## AppSec gate/);
    assert.match(body, /appsec:gate-fail/);
    assert.match(body, /Unsafe eval usage/);
    assert.match(body, /TopicActiveChecker\.php:59/);
    assert.match(body, /eval\(\$payload\)/);
  });

  it("omits the AppSec gate section on a clean Sentinel report", () => {
    const body = buildPrBody(manifest, parity);
    assert.doesNotMatch(body, /## AppSec gate/);
    assert.match(body, /## Parity verification/);
  });
});
