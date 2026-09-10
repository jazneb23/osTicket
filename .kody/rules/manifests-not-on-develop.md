---
title: "Manifests never land on develop"
scope: "pull-request"
path: ["orchestrator/manifests/**", "orchestrator/.state/**"]
severity_min: "high"
languages: ["jsts"]
buckets: ["style-conventions"]
enabled: true
---

@kody-sync

## Instructions

Runtime cartographer cache belongs in gitignored `orchestrator/.state/`. The only extra copy allowed is `orchestrator/manifests/MOD-*-manifest.json` on `strangler/MOD-*` PR branches so GitHub Actions can run parity.

- Reject adding `orchestrator/manifests/` files onto `develop`.
- Reject new parallel runtime caches outside `orchestrator/.state/` (except that PR-branch manifest copy).
- Flag a PR whose base is `develop` if it commits `orchestrator/manifests/MOD-*-manifest.json`. `/cleanup` removes that path by deleting the strangler branch, not by merging it.

## Examples

### Bad example
A PR targeting `develop` adds `orchestrator/manifests/MOD-25-manifest.json`.

### Good example
The manifest copy exists only on `strangler/MOD-25` so CI can check it out. It is never merged to `develop`.
