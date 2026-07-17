#!/usr/bin/env bash
# Starts Docker daemon and bootstraps osTicket Compose (web + db + schema/seeds).
# Idempotent — safe on every cloud agent wake.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Always use the local DinD daemon from .cursor/Dockerfile — never a forwarded host socket.
unset DOCKER_HOST

echo "Starting Docker daemon..."
if ! sudo service docker status >/dev/null 2>&1; then
  sudo service docker start
fi

echo "Waiting for Docker socket..."
for i in $(seq 1 30); do
  if [ -S /var/run/docker.sock ]; then
    break
  fi
  if [ "$i" -eq 30 ]; then
    echo "Docker socket did not appear in time" >&2
    exit 1
  fi
  sleep 1
done

# Cloud Agent shells often lack an active docker group despite ubuntu being in it.
# Widen socket perms on this ephemeral DinD VM so harness `docker compose exec` works.
sudo chown root:docker /var/run/docker.sock 2>/dev/null || true
if ! docker info >/dev/null 2>&1; then
  sudo chmod 666 /var/run/docker.sock
fi

for i in $(seq 1 30); do
  if docker info >/dev/null 2>&1; then
    echo "Docker is ready."
    break
  fi
  if [ "$i" -eq 30 ]; then
    echo "Docker daemon did not become ready in time" >&2
    docker info 2>&1 || true
    exit 1
  fi
  sleep 1
done

bash scripts/ci-docker-bootstrap.sh
