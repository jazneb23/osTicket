import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { publishPathsForTicket } from "./gitPublish";

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
