#!/usr/bin/env bash
# Starts Docker daemon and bootstraps osTicket Compose (web + db + schema/seeds).
# Idempotent — safe on every cloud agent wake / Automation tick.
#
# DinD on Cursor AnyOS is flaky: fuse-overlayfs is documented, vfs often works
# when fuse cannot create /var/run/docker.sock. This script tries both.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

unset DOCKER_HOST

log() {
  printf '[start.sh] %s\n' "$*"
}

fix_socket_perms() {
  if [ ! -S /var/run/docker.sock ]; then
    return 1
  fi
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
  id -nG >&2 || true
  ls -la /dev/fuse >&2 2>/dev/null || log "no /dev/fuse (fuse-overlayfs may fail)"
  ls -la /var/run/docker.sock >&2 2>/dev/null || log "no /var/run/docker.sock"
  pgrep -af 'dockerd|containerd' >&2 2>/dev/null || log "no dockerd/containerd process"
  cat /etc/docker/daemon.json >&2 2>/dev/null || true
  sudo service docker status >&2 2>/dev/null || true
  for f in /tmp/docker-service-start.log /tmp/dockerd.log; do
    if sudo test -f "$f" 2>/dev/null; then
      log "$(basename "$f"):"
      sudo tail -n 120 "$f" >&2 || true
    fi
  done
}

stop_dockerd() {
  sudo service docker stop >/dev/null 2>&1 || true
  if [ -f /var/run/docker.pid ]; then
    sudo kill -9 "$(sudo cat /var/run/docker.pid 2>/dev/null)" 2>/dev/null || true
  fi
  sudo killall -9 dockerd containerd docker-containerd 2>/dev/null || true
  sleep 2
  sudo rm -f /var/run/docker.sock /var/run/docker.pid
}

write_storage_driver() {
  local driver="$1"
  sudo mkdir -p /etc/docker
  # Must match how we launch dockerd. Passing --storage-driver=X while
  # daemon.json says Y makes dockerd exit immediately:
  #   "directives are specified both as a flag and in the configuration file"
  sudo tee /etc/docker/daemon.json >/dev/null <<EOF
{
  "storage-driver": "${driver}"
}
EOF
}

wait_for_docker() {
  local seconds="${1:-45}"
  local i
  for i in $(seq 1 "$seconds"); do
    fix_socket_perms || true
    if docker_ready; then
      return 0
    fi
    sleep 1
  done
  return 1
}

start_dockerd_with_driver() {
  local driver="$1"
  log "Launching dockerd (storage-driver=${driver} via daemon.json)"
  write_storage_driver "$driver"
  # Prior dockerd runs create root-owned /tmp/dockerd.log. Truncating as the
  # non-root agent user fails under set -e ("Permission denied") and aborts
  # before dockerd even launches — clear/recreate with sudo.
  sudo rm -f /tmp/dockerd.log
  sudo touch /tmp/dockerd.log
  sudo chmod 666 /tmp/dockerd.log
  # Do NOT pass --storage-driver here — it conflicts with daemon.json.
  sudo sh -c "nohup dockerd \
    --host=unix:///var/run/docker.sock \
    >/tmp/dockerd.log 2>&1 &"
}

bring_up_docker() {
  # If docker info already works, keep it. Earlier logic killed a working
  # daemon whenever /var/run/docker.sock failed a strict -S/perms check, then
  # could not restart. That caused a restart death spiral.
  if docker_ready; then
    fix_socket_perms || true
    if docker_ready; then
      log "Docker is already running."
      return 0
    fi
    log "Docker became unusable while fixing socket perms; will restart."
  fi

  if ! command -v docker >/dev/null 2>&1; then
    log "Docker CLI missing — this VM is not using .cursor/Dockerfile."
    log "Delete any personal/team snapshot override and rebuild from the repo Dockerfile."
    return 1
  fi

  # Prefer vfs on Cursor AnyOS; fuse-overlayfs in the image often leaves a
  # dockerd process with no usable socket.
  write_storage_driver vfs

  log "Starting Docker via service wrapper..."
  sudo sh -c 'service docker start >/tmp/docker-service-start.log 2>&1' || \
    log "service docker start returned non-zero"

  if wait_for_docker 25; then
    return 0
  fi

  log "Docker not ready after service start; restarting with explicit drivers..."
  stop_dockerd

  local driver
  for driver in vfs fuse-overlayfs; do
    start_dockerd_with_driver "$driver"
    if wait_for_docker 40; then
      log "Docker is ready (storage-driver=${driver})."
      return 0
    fi
    log "storage-driver=${driver} failed; trying next fallback"
    stop_dockerd
  done

  return 1
}

if ! bring_up_docker; then
  log "Docker daemon failed to start"
  dump_docker_diagnostics
  exit 1
fi

# Under set -e, fix_socket_perms must not abort the script on a transient miss —
# that was exiting 1 right after "Docker is already running" with no diagnostics.
if ! fix_socket_perms || ! docker_ready; then
  log "Docker socket not usable after bring-up"
  dump_docker_diagnostics
  exit 1
fi

log "Docker is ready."
docker version 2>/dev/null || sudo docker version || true

bash scripts/ci-docker-bootstrap.sh
