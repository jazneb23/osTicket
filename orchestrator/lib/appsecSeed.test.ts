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
import { seedAppsecDemoFinding } from "./appsecSeed";
import type { SeamManifest } from "./types";

const BASE_SERVICE = `<?php
class TeamEnabledChecker {
    public function isEnabled($flags) {
        return $flags & 0x0001;
    }
}
`;

function manifestFor(
  ticketId: string,
  extractionTarget: string
): SeamManifest {
  return {
    ticketId,
    entryPoint: "Team::isEnabled()",
    coreLogic: "flags check",
    consumers: [],
    inputShape: "flags",
    outputShape: "bool",
    sideEffects: [],
    constraints: [],
    extractionTarget,
  };
}

describe("seedAppsecDemoFinding", () => {
  const originalCwd = process.cwd();
  let dir: string;

  before(() => {
    dir = mkdtempSync(join(tmpdir(), "osticket-appsec-seed-"));
    chdir(dir);
  });

  after(() => {
    chdir(originalCwd);
    rmSync(dir, { recursive: true, force: true });
  });

  it("plants an OpenSSH private-key header in the MOD-30 extracted service", () => {
    mkdirSync("include/Services", { recursive: true });
    const path = "include/Services/TeamEnabledChecker.php";
    writeFileSync(path, BASE_SERVICE);

    const result = seedAppsecDemoFinding(manifestFor("MOD-30", path));

    const php = readFileSync(path, "utf-8");
    assert.equal(result, "secret");
    assert.match(php, /DEMO-ONLY AppSec seed \(MOD-30\)/);
    assert.match(php, /BEGIN OPENSSH PRIVATE KEY/);
    assert.match(php, /function isEnabled/);
    assert.ok(php.trimEnd().endsWith("}"), "class closing brace preserved");
  });

  it("plants an eval-based HIGH SAST sink in the MOD-31 extracted service", () => {
    mkdirSync("include/Services", { recursive: true });
    const path = "include/Services/TopicActiveChecker.php";
    writeFileSync(path, BASE_SERVICE.replace("TeamEnabledChecker", "TopicActiveChecker"));

    const result = seedAppsecDemoFinding(manifestFor("MOD-31", path));

    const php = readFileSync(path, "utf-8");
    assert.equal(result, "sast");
    assert.match(php, /DEMO-ONLY AppSec seed \(MOD-31\)/);
    assert.match(php, /eval\s*\(\s*\$payload\s*\)/);
  });

  it("does not plant on MOD-25 or MOD-27", () => {
    mkdirSync("include/Services", { recursive: true });
    const path = "include/Services/SlaGracePeriodCalculator.php";
    writeFileSync(path, BASE_SERVICE);

    assert.equal(seedAppsecDemoFinding(manifestFor("MOD-25", path)), null);
    assert.equal(seedAppsecDemoFinding(manifestFor("MOD-27", path)), null);
    assert.equal(readFileSync(path, "utf-8"), BASE_SERVICE);
  });

  it("is idempotent when the seed marker is already present", () => {
    mkdirSync("include/Services", { recursive: true });
    const path = "include/Services/TeamEnabledChecker.php";
    writeFileSync(path, BASE_SERVICE);

    seedAppsecDemoFinding(manifestFor("MOD-30", path));
    const once = readFileSync(path, "utf-8");
    seedAppsecDemoFinding(manifestFor("MOD-30", path));
    assert.equal(readFileSync(path, "utf-8"), once);
  });
});
