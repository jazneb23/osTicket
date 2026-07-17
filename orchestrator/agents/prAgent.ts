import {
  buildPrBody,
  getBaseBranch,
  stranglerBranchName,
} from "../lib/gitPublish";
import {
  logRunEnd,
  logRunStart,
  streamRunWithProgress,
  withCloudAgent,
} from "../lib/sdk";
import { requireExtractionTarget, requireFacadeFile } from "../lib/manifest";
import type { SeamManifest, ParityReport } from "../lib/types";

export async function prAgent(manifest: SeamManifest, report: ParityReport) {
  const extractionTarget = requireExtractionTarget(manifest);
  const facadeFile = requireFacadeFile(manifest);
  const branch = stranglerBranchName(manifest.ticketId);
  const base = getBaseBranch();
  const title = `chore(${manifest.ticketId}): strangler extraction`;
  const body = buildPrBody(manifest, report);
  const repoUrl = process.env.GITHUB_REPO_URL ?? "";

  return withCloudAgent(
    async (agent) => {
      const run = await agent.send(`
Open a pull request for the strangler extraction for ticket ${manifest.ticketId}.

Head branch (already pushed): ${branch}
Base branch: ${base}
Repo: ${repoUrl || "(current remote)"}

The PR should include the delegating extraction changes:
- ${extractionTarget} (new extracted service)
- ${facadeFile} (patched facade delegating to the service)

Use this exact title:
${title}

Use the following PR body:

${body}

Create the PR with gh. Prefer:
  gh pr create --repo jazneb23/osTicket --base ${base} --head ${branch} --title "..." --body "..."

Do not open the PR against upstream osTicket/osTicket. Target the fork base branch ${base}.
If an open PR for head ${branch} already exists, return that URL instead of creating another.
  `);

      logRunStart("pr-agent");
      const result = await streamRunWithProgress(run, "pr-agent");
      logRunEnd("pr-agent", result.status);
      if (result.status === "error") {
        throw new Error(result.error?.message ?? "PR agent run failed");
      }
      const prUrl = result.git?.branches?.[0]?.prUrl ?? "";
      if (!prUrl) {
        throw new Error(
          "PR agent finished without a PR URL (check gh auth and --repo/--base)"
        );
      }
      return { prUrl };
    },
    { model: "composer-2.5", name: `pr-agent · ${manifest.ticketId}` }
  );
}
