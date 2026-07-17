#!/usr/bin/env bash
# Local demo bootstrap: MySQL + PHP for the parity harness (no cloud DinD).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is not installed. Install Docker Desktop, then re-run this script." >&2
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "Docker daemon is not running. Start Docker Desktop, then re-run this script." >&2
  exit 1
fi

bash scripts/ci-docker-bootstrap.sh

echo ""
echo "Local parity stack is ready (db :3306, web :8080)."
echo "Next: npx tsx orchestrator/listener.ts"
echo "Then move one MOD-* ticket to Ready in Linear."
