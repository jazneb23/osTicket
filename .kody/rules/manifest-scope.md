---
title: "Only manifest-named files; no vendor edits"
scope: "pull-request"
path: ["**/*"]
severity_min: "high"
languages: ["php", "jsts"]
buckets: ["style-conventions"]
enabled: true
---

@kody-sync

## Instructions

Seam work must stay within the current ticket's manifest (`facadeFile`, `extractionTarget`, and the matching harness when the ticket requires one).

- Prefer changes in `orchestrator/`, `include/Services/`, `legacy/harness/`, and `orchestrator/fixtures/`.
- Reject edits to `include/*/vendor/`, mpdf/laminas/pear/fpdf vendor trees, or unrelated osTicket files unless the seam manifest explicitly names them.
- Flag unrelated PHP refactors bundled with an extraction.
- Do not treat upstream osTicket README or contribution guidance as the source of truth for this demo repo.

## Examples

### Bad example
A strangler PR for `SLA::addGracePeriod` also reformats `include/class.ticket.php` and edits `include/mpdf/vendor/**`.

### Good example
The PR only touches the manifest `facadeFile`, `extractionTarget`, and (if needed) the named harness plus that ticket's fixtures.
