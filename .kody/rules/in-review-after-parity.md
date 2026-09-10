---
title: "Linear In Review and Slack success only after gatePassed"
scope: "file"
path: ["orchestrator/lib/linear.ts", "orchestrator/lib/slack.ts", "orchestrator/pipeline.ts"]
severity_min: "critical"
languages: ["jsts"]
buckets: ["error-handling"]
enabled: true
---

@kody-sync

## Instructions

When reviewing `orchestrator/lib/linear.ts`, `orchestrator/lib/slack.ts`, or `orchestrator/pipeline.ts`:

- Reject Linear status moves to In Review or Slack success notifications that fire before `report.gatePassed === true`.
- Reject new comment or Slack payload formats alongside existing helpers (`buildInReviewComment`, `buildParityFailedComment`, `notifyPrOpened`, and the other helpers already in those files).
- Flag success-path logic on a failed parity gate.
- Kodus reviewing the GitHub PR is not a substitute for this gate. Do not treat a Kody comment as permission to skip `gatePassed`.

## Examples

### Bad example
```typescript
await setIssueStatus(ticketId, STATUS_IN_REVIEW);
await notifyPrOpened(ticketId, prUrl);
// verifier has not run, or report.gatePassed is false
```

### Good example
```typescript
if (!report.gatePassed) {
  await handleParityFailure(ticketId, report);
  return;
}
const { prUrl } = await prAgent(manifest, report);
await notifyPrOpened(ticketId, prUrl);
await addIssueComment(ticketId, buildInReviewComment(manifest, report, prUrl));
await setIssueStatus(ticketId, STATUS_IN_REVIEW);
```
