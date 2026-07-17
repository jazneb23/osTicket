import { execFileSync } from "child_process";

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
