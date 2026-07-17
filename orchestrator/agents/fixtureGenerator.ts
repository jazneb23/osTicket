import * as fs from "fs";
import * as path from "path";
import { CLOUD_AGENT_HARD_RULES } from "../lib/cloudAgentGuardrails";
import {
  logRunEnd,
  logRunStart,
  parseJsonResult,
  streamRunWithProgress,
  withCloudAgent,
} from "../lib/sdk";
import { requireHarnessScript } from "../lib/manifest";
import { logAgentLine } from "../lib/terminal";
import type { Fixture, SeamManifest } from "../lib/types";

const FIXTURE_ATTEMPTS = 3;
const FIXTURE_RETRY_DELAY_MS = 8000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function fixtureDir(ticketId: string): string {
  return `orchestrator/fixtures/${ticketId}`;
}

async function generateFixturesViaCloud(
  manifest: SeamManifest,
  harnessScript: string,
  dir: string
): Promise<Fixture[]> {
  return withCloudAgent(
    async (agent) => {
      const run = await agent.send(`
${CLOUD_AGENT_HARD_RULES}

Seam manifest for ticket ${manifest.ticketId}:
${JSON.stringify(manifest, null, 2)}

Propose 4-6 fixture input definitions that exercise the real distinct behavioral
branches described in coreLogic and sideEffects above — not generic placeholders,
but cases grounded in this ticket's actual logic paths.

Harness script: ${harnessScript}
Harness input shape: ${manifest.harnessInputShape ?? "(see inputShape in manifest)"}

Each fixture must cover a different branch (guards, fallbacks, normal path, edge
cases — only where this manifest documents them).

For each fixture provide:
- name: kebab-case identifier unique within this ticket
- branch: one-line description of which behavioral branch this exercises
- input: JSON object with the fields the harness expects (per harnessInputShape)

Do NOT include an expected field — baseline outputs are captured automatically
by the pipeline after fixture generation.
Do NOT run Docker, harnesses, or the parity verifier — only propose fixture inputs.

Respond with ONLY a valid JSON array of fixture objects. No prose before or after.
  `);

      logRunStart("fixture-generator");
      const result = await streamRunWithProgress(run, "fixture-generator");
      logRunEnd("fixture-generator", result.status);
      if (result.status === "error") {
        const detail = result.error?.message ?? "no error message from cloud agent";
        throw new Error(`Fixture generator cloud run failed: ${detail}`);
      }

      const fixtures = parseJsonResult<Fixture[]>(result.result, []);
      if (fixtures.length < 4 || fixtures.length > 6) {
        throw new Error(
          `Fixture generator returned ${fixtures.length} fixtures; expected 4-6`
        );
      }

      for (const fixture of fixtures) {
        delete fixture.expected;
        const filePath = path.join(dir, `${fixture.name}.json`);
        fs.writeFileSync(filePath, JSON.stringify(fixture, null, 2) + "\n");
        logAgentLine("fixture-generator", `wrote ${filePath}`);
      }

      return fixtures;
    },
    { model: "claude-sonnet-5", name: `fixture-generator · ${manifest.ticketId}` }
  );
}

export async function fixtureGenerator(manifest: SeamManifest): Promise<Fixture[]> {
  const harnessScript = requireHarnessScript(manifest);
  const dir = fixtureDir(manifest.ticketId);
  fs.mkdirSync(dir, { recursive: true });

  const existing = fs.readdirSync(dir).filter((f) => f.endsWith(".json"));
  if (existing.length > 0) {
    logAgentLine(
      "fixture-generator",
      `${existing.length} fixture(s) exist in ${dir}, skipping`
    );
    return existing.map((f) =>
      JSON.parse(fs.readFileSync(path.join(dir, f), "utf-8")) as Fixture
    );
  }

  let lastError: unknown;
  for (let attempt = 1; attempt <= FIXTURE_ATTEMPTS; attempt++) {
    try {
      if (attempt > 1) {
        logAgentLine(
          "fixture-generator",
          `retry ${attempt}/${FIXTURE_ATTEMPTS} after cloud agent failure`
        );
      }
      return await generateFixturesViaCloud(manifest, harnessScript, dir);
    } catch (err) {
      lastError = err;
      const msg = err instanceof Error ? err.message : String(err);
      logAgentLine("fixture-generator", `attempt ${attempt}/${FIXTURE_ATTEMPTS} failed: ${msg}`);
      if (attempt < FIXTURE_ATTEMPTS) {
        await sleep(FIXTURE_RETRY_DELAY_MS * attempt);
      }
    }
  }

  throw lastError instanceof Error
    ? lastError
    : new Error("Fixture generator run failed after retries");
}
