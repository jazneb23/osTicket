import * as fs from "fs";
import { requireExtractionTarget } from "./manifest";
import type { SeamManifest } from "./types";

export const APPSEC_SECRET_TICKET = "MOD-30";
export const APPSEC_SAST_TICKET = "MOD-31";

export const APPSEC_SEED_MARKER = "DEMO-ONLY AppSec seed";

export type AppsecSeedKind = "secret" | "sast";

const SECRET_SNIPPET = `
    /**
     * DEMO-ONLY AppSec seed (MOD-30). Not called from the facade. Do not "fix".
     * Sentinel should halt on this leaked key material.
     */
    private const DEMO_OPENSSH_KEY = '-----BEGIN OPENSSH PRIVATE KEY-----\\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmU=\\n-----END OPENSSH PRIVATE KEY-----';
`;

const SAST_SNIPPET = `
    /**
     * DEMO-ONLY AppSec seed (MOD-31). Not called from the facade. Do not "fix".
     * Aikido SAST should flag eval() as HIGH; Sentinel reports it but does not halt.
     */
    public function demoUnsafePayload($payload) {
        return eval($payload);
    }
`;

export function seedKindForTicket(ticketId: string): AppsecSeedKind | null {
  if (ticketId === APPSEC_SECRET_TICKET) return "secret";
  if (ticketId === APPSEC_SAST_TICKET) return "sast";
  return null;
}

/**
 * After Strangler, plant a documented demo finding in the extracted service.
 * Idempotent. Leaves MOD-25 / MOD-27 (and every other ticket) untouched.
 */
export function seedAppsecDemoFinding(manifest: SeamManifest): AppsecSeedKind | null {
  const kind = seedKindForTicket(manifest.ticketId);
  if (!kind) {
    return null;
  }

  const path = requireExtractionTarget(manifest);
  if (!fs.existsSync(path)) {
    throw new Error(
      `Cannot seed AppSec demo finding: ${path} does not exist (extractor must run first)`
    );
  }

  const source = fs.readFileSync(path, "utf-8");
  if (source.includes(APPSEC_SEED_MARKER)) {
    return kind;
  }

  const snippet = kind === "secret" ? SECRET_SNIPPET : SAST_SNIPPET;
  const idx = source.lastIndexOf("}");
  if (idx === -1) {
    throw new Error(`Cannot seed AppSec demo finding: no class closing brace in ${path}`);
  }

  fs.writeFileSync(path, source.slice(0, idx) + snippet + source.slice(idx));
  return kind;
}
