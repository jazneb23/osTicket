import { execFileSync } from "child_process";
import type { SeamManifest, ParityReport } from "./types";

export type PublishPaths = {
  ticketId: string;
  branch: string;
  /** Extra paths to stage (e.g. facadeFile, extractionTarget). */
  paths?: string[];
  harnessScript?: string;
};

function git(args: string[], inherit = false): string {
  return execFileSync("git", args, {
    encoding: "utf-8",
    stdio: inherit ? "inherit" : ["ignore", "pipe", "pipe"],
  }).trim();
}

/** Base branch for demo PRs; demo extractions never merge into this branch. */
export function getBaseBranch(): string {
  const base = process.env.GITHUB_DEMO_BRANCH;
  if (!base) {
    throw new Error(
      "GITHUB_DEMO_BRANCH is required for per-ticket strangler branches"
    );
  }
  return base;
}

export function stranglerBranchName(ticketId: string): string {
  return `strangler/${ticketId}`;
}

function currentBranchName(): string {
  try {
    return git(["rev-parse", "--abbrev-ref", "HEAD"]);
  } catch {
    return "";
  }
}

function workingTreeDirty(): boolean {
  return (
    execFileSync("git", ["status", "--porcelain"], { encoding: "utf-8" }).trim()
      .length > 0
  );
}

/**
 * Switch to the per-ticket publish branch after parity passes.
 *
 * Called at publish time only — not at pipeline entry — so extractor/strangler
 * artifacts accumulated on the current checkout are preserved. Fetches the
 * strangler head explicitly so fresh clones see an existing remote branch.
 */
export function ensureStranglerBranch(ticketId: string): string {
  const base = getBaseBranch();
  const branch = stranglerBranchName(ticketId);

  execFileSync("git", ["fetch", "origin", base], { stdio: "inherit" });

  let remoteExists = false;
  try {
    execFileSync("git", ["fetch", "origin", branch], { stdio: "inherit" });
    git(["rev-parse", "--verify", `origin/${branch}`]);
    remoteExists = true;
  } catch {
    remoteExists = false;
  }

  const onBranch = currentBranchName() === branch;
  const dirty = workingTreeDirty();

  if (onBranch) {
    if (remoteExists && !dirty) {
      try {
        execFileSync("git", ["pull", "--ff-only", "origin", branch], {
          stdio: "inherit",
        });
      } catch {
        // Diverged local commits; publish push will surface the conflict.
      }
    } else if (!remoteExists) {
      execFileSync("git", ["push", "-u", "origin", branch], {
        stdio: "inherit",
      });
    }
    return branch;
  }

  // Not on the ticket branch — keep uncommitted seam artifacts when present.
  if (dirty) {
    execFileSync("git", ["checkout", "-B", branch], { stdio: "inherit" });
    return branch;
  }

  if (remoteExists) {
    execFileSync("git", ["checkout", "-B", branch, `origin/${branch}`], {
      stdio: "inherit",
    });
    try {
      execFileSync("git", ["pull", "--ff-only", "origin", branch], {
        stdio: "inherit",
      });
    } catch {
      // Local-only resume; push will reconcile on publish.
    }
  } else {
    execFileSync("git", ["checkout", "-B", branch, `origin/${base}`], {
      stdio: "inherit",
    });
    execFileSync("git", ["push", "-u", "origin", branch], { stdio: "inherit" });
  }

  return branch;
}

/** Paths staged for a ticket — avoids peer fixtures and global parity.json. */
export function publishPathsForTicket(
  ticketId: string,
  extraPaths: string[] = [],
  harnessScript?: string
): string[] {
  const paths = new Set<string>([
    `orchestrator/fixtures/${ticketId}`,
    ...extraPaths.filter(Boolean),
  ]);
  if (harnessScript) {
    paths.add(harnessScript);
  }
  return [...paths];
}

/**
 * Commit and push strangler artifacts on the per-ticket branch.
 * No-ops when the working tree has nothing to publish for the staged paths.
 */
export function publishArtifactsForPr(options: PublishPaths): {
  published: boolean;
  sha?: string;
} {
  const { ticketId, branch, paths = [], harnessScript } = options;
  const toAdd = publishPathsForTicket(ticketId, paths, harnessScript);

  for (const p of toAdd) {
    try {
      execFileSync("git", ["add", "--", p], { stdio: "pipe" });
    } catch {
      // Path may not exist yet for partial resumes; ignore.
    }
  }

  const status = execFileSync("git", ["status", "--porcelain"], {
    encoding: "utf-8",
  }).trim();
  if (!status) {
    return { published: false };
  }

  try {
    execFileSync("git", ["fetch", "origin", branch], { stdio: "inherit" });
    execFileSync("git", ["pull", "--rebase", "origin", branch], {
      stdio: "inherit",
    });
  } catch {
    // First publish on a new branch — no remote head yet.
  }

  const message = `chore(${ticketId}): publish strangler artifacts for PR`;
  execFileSync("git", ["commit", "-m", message], { stdio: "inherit" });
  execFileSync("git", ["push", "-u", "origin", branch], { stdio: "inherit" });
  const sha = execFileSync("git", ["rev-parse", "HEAD"], {
    encoding: "utf-8",
  }).trim();
  return { published: true, sha };
}

export function buildPrBody(manifest: SeamManifest, report: ParityReport): string {
  return [
    "## Summary",
    "",
    `Seam manifest for ticket ${manifest.ticketId}:`,
    `- Entry point: ${manifest.entryPoint}`,
    `- Core logic: ${manifest.coreLogic}`,
    `- Consumers: ${manifest.consumers.join(", ")}`,
    `- Input: ${manifest.inputShape}`,
    `- Output: ${manifest.outputShape}`,
    `- Side effects: ${manifest.sideEffects.join("; ")}`,
    `- Constraints: ${manifest.constraints.join("; ")}`,
    "",
    "## Parity verification",
    "",
    `${report.passed}/${report.totalCases} golden fixture cases passed.`,
    "",
    "## Note",
    "",
    "This is a delegating extraction, not a reimplementation. The new service at",
    `${manifest.extractionTarget} wraps existing logic from ${manifest.coreLogic}`,
    "rather than reimplementing it.",
    "",
    "**Demo only — do not merge into the base branch.**",
  ].join("\n");
}

function ghRepoArgs(): string[] {
  const url = process.env.GITHUB_REPO_URL;
  if (!url) return [];
  const match = url.match(/github\.com[:/]([^/]+)\/([^/?.]+)/);
  if (!match) return [];
  return ["--repo", `${match[1]}/${match[2]}`];
}

function requireGhCli(): void {
  try {
    execFileSync("gh", ["--version"], { stdio: "ignore" });
  } catch {
    throw new Error(
      "GitHub CLI (gh) is required for the demo PR step. Install gh and run `gh auth login`."
    );
  }
  try {
    execFileSync("gh", ["auth", "status"], { stdio: "ignore" });
  } catch {
    throw new Error(
      "gh is not authenticated. Run `gh auth login` before starting the listener."
    );
  }
}

function findOpenPrUrl(headBranch: string): string | undefined {
  try {
    const raw = execFileSync(
      "gh",
      [
        "pr",
        "list",
        ...ghRepoArgs(),
        "--head",
        headBranch,
        "--state",
        "open",
        "--limit",
        "1",
        "--json",
        "url",
      ],
      { encoding: "utf-8", stdio: ["ignore", "pipe", "pipe"] }
    ).trim();
    const rows = JSON.parse(raw) as Array<{ url?: string }>;
    return rows[0]?.url?.trim() || undefined;
  } catch {
    return undefined;
  }
}

/** Open (or return existing) PR with explicit base/head branches via local gh. */
export function openPullRequest(options: {
  ticketId: string;
  branch: string;
  title: string;
  body: string;
}): { prUrl: string } {
  requireGhCli();
  const base = getBaseBranch();
  const { branch, title, body } = options;

  const existing = findOpenPrUrl(branch);
  if (existing) {
    console.log(`PR already open for ${branch}: ${existing}`);
    return { prUrl: existing };
  }

  try {
    const output = execFileSync(
      "gh",
      [
        "pr",
        "create",
        ...ghRepoArgs(),
        "--base",
        base,
        "--head",
        branch,
        "--title",
        title,
        "--body",
        body,
      ],
      { encoding: "utf-8", stdio: ["ignore", "pipe", "inherit"] }
    ).trim();

    const urlMatch = output.match(/https:\/\/github\.com\/\S+/);
    const prUrl = urlMatch?.[0] ?? output;
    if (!prUrl.startsWith("https://")) {
      throw new Error(`gh pr create returned unexpected output: ${output}`);
    }
    return { prUrl };
  } catch (err) {
    const recovered = findOpenPrUrl(branch);
    if (recovered) {
      return { prUrl: recovered };
    }
    const msg = err instanceof Error ? err.message : String(err);
    throw new Error(
      `Failed to open PR (${branch} → ${base}): ${msg}. Check \`gh auth status\` and that ${branch} is pushed to origin.`
    );
  }
}
