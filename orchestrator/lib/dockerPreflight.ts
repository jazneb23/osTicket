import { execSync } from "child_process";

/**
 * Parity runs on local Docker Compose (see scripts/local-demo-start.sh).
 * Fail fast with a clear message instead of mid-pipeline harness errors.
 */
export function assertLocalDockerReady(): void {
  try {
    execSync("docker info", { stdio: "pipe" });
  } catch {
    throw new Error(
      "Docker Desktop is not running. Start it, then run: bash scripts/local-demo-start.sh"
    );
  }

  try {
    execSync(
      'docker compose exec -T db mysql -uroot -posticket -e "SELECT 1"',
      { stdio: "pipe" }
    );
  } catch {
    throw new Error(
      "Parity stack (db + web) is not up. Run: bash scripts/local-demo-start.sh"
    );
  }
}
