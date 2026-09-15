import { execSync } from "child_process";

/**
 * Normalize harness `output` for the fixture contract (`string | null`).
 * ISO timestamps and other scalars stay strings. Objects/arrays (e.g. a
 * Topic harness returning `{isActive, isEnabled}`) become stable JSON so
 * baseline capture and the verifier compare by value, not object identity.
 */
export function canonicalizeHarnessOutput(output: unknown): string | null {
  if (output === null || output === undefined) {
    return null;
  }
  if (typeof output === "string") {
    return output;
  }
  return JSON.stringify(output);
}

export function harnessOutputsEqual(actual: unknown, expected: unknown): boolean {
  return canonicalizeHarnessOutput(actual) === canonicalizeHarnessOutput(expected);
}

export function runHarness(
  script: string,
  input: Record<string, unknown>
): string | null {
  const raw = execSync(
    `docker compose exec -T web php ${script} '${JSON.stringify(input)}'`
  ).toString();
  const parsed = JSON.parse(raw) as { output?: unknown };
  return canonicalizeHarnessOutput(parsed.output);
}
