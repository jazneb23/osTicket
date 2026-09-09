import assert from "node:assert/strict";
import {
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { chdir } from "node:process";
import { after, before, describe, it } from "node:test";
import { committedManifestPath, copyManifestForPublish } from "./parityScope";

describe("copyManifestForPublish", () => {
  const originalCwd = process.cwd();
  let dir: string;

  before(() => {
    dir = mkdtempSync(join(tmpdir(), "osticket-manifest-"));
    chdir(dir);
  });

  after(() => {
    chdir(originalCwd);
    rmSync(dir, { recursive: true, force: true });
  });

  it("copies the runtime .state manifest onto orchestrator/manifests for the PR", () => {
    mkdirSync("orchestrator/.state", { recursive: true });
    writeFileSync(
      "orchestrator/.state/MOD-28-manifest.json",
      JSON.stringify({ ticketId: "MOD-28" })
    );

    const dest = copyManifestForPublish("MOD-28");

    assert.equal(dest, "orchestrator/manifests/MOD-28-manifest.json");
    const copied = JSON.parse(readFileSync(dest, "utf-8"));
    assert.equal(copied.ticketId, "MOD-28");
  });

  it("rejects ticket ids that are not MOD-<digits>", () => {
    assert.throws(
      () => committedManifestPath("../etc/passwd"),
      /Invalid ticket id/
    );
  });
});
