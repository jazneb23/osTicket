import * as fs from "fs";

export function attemptsPath(ticketId: string): string {
  return `orchestrator/.state/${ticketId}-attempts.json`;
}

export function readAttempts(ticketId: string): number {
  const path = attemptsPath(ticketId);
  if (!fs.existsSync(path)) {
    return 0;
  }
  try {
    const data = JSON.parse(fs.readFileSync(path, "utf-8")) as { count?: number };
    return typeof data.count === "number" && data.count >= 0 ? data.count : 0;
  } catch {
    return 0;
  }
}

export function incrementAttempts(ticketId: string): number {
  const count = readAttempts(ticketId) + 1;
  fs.mkdirSync("orchestrator/.state", { recursive: true });
  fs.writeFileSync(attemptsPath(ticketId), JSON.stringify({ count }, null, 2));
  return count;
}

export function resetAttempts(ticketId: string): void {
  const path = attemptsPath(ticketId);
  if (fs.existsSync(path)) {
    fs.unlinkSync(path);
  }
}

export function maxStageRetries(): number {
  const raw = process.env.PIPELINE_MAX_STAGE_RETRIES;
  if (raw === undefined || raw === "") {
    return 3;
  }
  const parsed = parseInt(raw, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : 3;
}
