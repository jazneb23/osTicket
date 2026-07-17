/**
 * Hard rules for nested withCloudAgent stages (cartographer, fixture-generator).
 * Parity Docker / compose / listener run on the demo Mac — never in the cloud VM.
 */
export const CLOUD_AGENT_HARD_RULES = `
HARD ENVIRONMENT RULES (non-negotiable):
- You are a nested Cursor cloud agent. Parity Docker runs on the operator's Mac,
  NOT in this VM. Do not attempt to "fix" or bootstrap the parity stack here.
- Do NOT run: docker, dockerd, docker compose, service docker, local-demo-start.sh,
  ci-docker-bootstrap.sh, or any compose/up/exec against db/web.
- Do NOT run: the orchestrator pipeline, listener.ts, capture-and-verify, npm test,
  or tmux sessions that launch those.
- Do NOT spend turns debugging Docker sockets, group membership, or DinD.
- Do NOT treat AGENTS.md / onboarding runbook Docker instructions as your job —
  those are for the local demo machine only.
- Stay on the assigned task: read relevant PHP/code, then emit the required JSON.
`.trim();
