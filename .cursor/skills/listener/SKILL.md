---
name: listener
description: >-
  Starts local demo Docker then the orchestrator listener. Use when the user
  asks to start the listener, run the local demo listener, or invokes /listener.
---

# Listener — start local demo

Run exactly these two commands from the repo root. Do nothing else.

1. Start Docker / Compose:

```bash
bash scripts/local-demo-start.sh
```

2. After that succeeds, start the listener (long-running — background it):

```bash
npx tsx orchestrator/listener.ts
```

Do not run parity, open PRs, touch Linear, edit files, or add extra steps.
