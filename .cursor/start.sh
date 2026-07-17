#!/usr/bin/env bash
# Starts Docker daemon and bootstraps osTicket Compose (web + db + schema/seeds).
# Idempotent — safe on every cloud agent wake / Automation tick.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Always use the local DinD daemon from .cursor/Dockerfile — never a forwarded host socket.
unset DOCKER_HOST

log() {
  printf '[start.sh] %s\n' "$*"
}

fix_socket_perms() {
  if [ ! -S /var/run/docker.sock ]; then
    return 1
  fi
  # Cloud Agent shells often lack an active docker group despite ubuntu being in it.
  sudo groupadd -f docker >/dev/null 2>&1 || true
  sudo usermod -aG docker "$(id -un)" >/dev/null 2>&1 || true
  sudo chown root:docker /var/run/docker.sock 2>/dev/null || true
  sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
}

docker_ready() {
  docker info >/dev/null 2>&1 || sudo docker info >/dev/null 2>&1
}

dump_docker_diagnostics() {
  log "--- docker diagnostics ---"
  command -v docker >/dev/null 2>&1 && docker --version >&2 || log "docker CLI missing"
  ls -la /var/run/docker.sock >&2 2>/dev/null || log "no /var/run/docker.sock"
  pgrep -af dockerd >&2 2>/dev/null || log "no dockerd process"
  sudo service docker status >&2 2>/dev/null || true
  if [ -f /tmp/docker-service-start.log ]; then
    log "service docker start log:"
    tail -n 80 /tmp/docker-service-start.log >&2 || true
  fi
  if [ -f /tmp/dockerd.log ]; then
    log "dockerd log:"
    tail -n 120 /tmp/dockerd.log >&2 || true
  fi
}

start_dockerd() {
  # Cursor AnyOS DinD reliably uses vfs; fuse-overlayfs often fails before the socket appears.
  local driver="${1:-vfs}"
  log "Launching dockerd (storage-driver=${driver})..."
  sudo sh -c "nohup dockerd \
    --host=unix:///var/run/docker.sock \
    --storage-driver=${driver} \
    >/tmp/dockerd.log 2>&1 &"
}

if ! command -v docker >/dev/null 2>&1; then
  log "Docker CLI missing — environment was not built from .cursor/Dockerfile"
  exit 1
fi

if docker_ready; then
  log "Docker is already running."
else
  log "Starting Docker daemon..."
  sudo sh -c 'service docker start >/tmp/docker-service-start.log 2>&1' || \
    log "service docker start failed; will launch dockerd directly"

  # If the sysv service did not bring dockerd up, start it ourselves with vfs.
  if ! pgrep -x dockerd >/dev/null 2>&1; then
    start_dockerd vfs
  fi
fi

log "Waiting for Docker socket..."
for i in $(seq 1 90); do
  if [ -S /var/run/docker.sock ]; then
    fix_socket_perms || true
    break
  fi

  # Service may have died silently — relaunch once mid-wait.
  if [ "$i" -eq 15 ] && ! pgrep -x dockerd >/dev/null 2>&1; then
    log "dockerd not running after 15s; relaunching with vfs"
    start_dockerd vfs
  fi

  if [ "$i" -eq 90 ]; then
    log "Docker socket did not appear in time"
    dump_docker_diagnostics
    exit 1
  fi
  sleep 1
done

fix_socket_perms

log "Waiting for Docker daemon..."
for i in $(seq 1 60); do
  if docker_ready; then
    log "Docker is ready."
    docker version 2>/dev/null || sudo docker version || true
    break
  fi
  if [ "$i" -eq 60 ]; then
    log "Docker daemon did not become ready in time"
    dump_docker_diagnostics
    exit 1
  fi
  fix_socket_perms || true
  sleep 1
done

bash scripts/ci-docker-bootstrap.sh
