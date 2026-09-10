---
title: "Do not skip the parity verifier or invent fixture expecteds"
scope: "pull-request"
path: ["orchestrator/**", "legacy/harness/**"]
severity_min: "critical"
languages: ["jsts", "php"]
buckets: ["testing"]
enabled: true
---

@kody-sync

## Instructions

This repository is a strangler-fig migration demo. The parity verifier is sacred.

- Reject any change that skips, weakens, bypasses, or stubs the verifier (`orchestrator/agents/verifier.ts`, `orchestrator/ci-parity-check.ts`, `orchestrator/capture-and-verify.ts`).
- Reject invented or guessed `expected` values in `orchestrator/fixtures/**`. Expected values must come from a real baseline capture of legacy behavior.
- Reject fixture edits made solely to make the verifier pass without a real harness run.
- Flag PRs that would open a pull request or move a Linear ticket to In Review when `gatePassed` is not genuinely true.

## Examples

### Bad example
A PR deletes verifier calls from `pipeline.ts` or hand-writes `"expected"` in a fixture JSON so the gate turns green.

### Good example
Fixtures keep baseline-captured `expected` values. The verifier still compares harness output to those values and is the sole authority for `gatePassed`.
