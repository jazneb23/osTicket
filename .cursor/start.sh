#!/usr/bin/env bash
# Starts Docker daemon and bootstraps osTicket Compose (web + db + schema/seeds).
# Idempotent — safe on every cloud agent wake / Automation tick.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Always use the local DinD daemon from .cursor/Dockerfile — never a forwarded host socket.
unset DOCKER_HOST

fix_socket_perms() {
  if [ ! -S /var/run/docker.sock ]; then
    return 1
  fi
  # Cloud Agent shells often lack an active docker group despite ubuntu being in it.
  sudo chown root:docker /var/run/docker.sock 2>/dev/null || true
  sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
}

docker_ready() {
  docker info >/dev/null 2>&1 || sudo docker info >/dev/null 2>&1
}

echo "Starting Docker daemon..."
# `service docker start` can return non-zero even when dockerd is coming up —
# never let that abort the script before we wait on the socket.
if ! docker_ready; then
  sudo service docker start >/dev/null 2>&1 || true
  # Fallback if the sysv service wrapper is missing / flaky on this image.
  if [ ! -S /var/run/docker.sock ]; then
    sudo dockerd >/tmp/dockerd.log 2>&1 &
  fi
fi

echo "Waiting for Docker socket..."
for i in $(seq 1 60); do
  if [ -S /var/run/docker.sock ]; then
    fix_socket_perms || true
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "Docker socket did not appear in time" >&2
    sudo service docker status >&2 || true
    tail -n 50 /tmp/dockerd.log >&2 2>/dev/null || true
    exit 1
  fi
  sleep 1
done

fix_socket_perms

echo "Waiting for Docker daemon..."
for i in $(seq 1 60); do
  if docker_ready; then
    echo "Docker is ready."
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "Docker daemon did not become ready in time" >&2
    ls -la /var/run/docker.sock >&2 || true
    docker info 2>&1 || true
    sudo docker info 2>&1 || true
    sudo service docker status >&2 || true
    tail -n 80 /tmp/dockerd.log >&2 2>/dev/null || true
    exit 1
  fi
  # Re-apply perms each loop — dockerd may recreate the socket.
  fix_socket_perms || true
  sleep 1
done

bash scripts/ci-docker-bootstrap.sh
