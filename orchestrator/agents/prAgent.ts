import {
  buildPrBody,
  openPullRequest,
  stranglerBranchName,
} from "../lib/gitPublish";
import { requireExtractionTarget, requireFacadeFile } from "../lib/manifest";
import type { SeamManifest, ParityReport } from "../lib/types";

/**
 * Open a demo PR via local `gh` on the machine running the listener.
 * Cloud agents lack GitHub integration permissions for `gh pr create`.
 */
export async function prAgent(manifest: SeamManifest, report: ParityReport) {
  requireExtractionTarget(manifest);
  requireFacadeFile(manifest);

  const branch = stranglerBranchName(manifest.ticketId);
  const title = `${manifest.ticketId}: Strangler extraction (${manifest.entryPoint})`;
  const body = buildPrBody(manifest, report);

  return openPullRequest({ ticketId: manifest.ticketId, branch, title, body });
}
