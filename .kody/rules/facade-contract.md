---
title: "Preserve facade signature and side effects; no service-to-facade recursion"
scope: "file"
path: ["include/Services/**", "include/class.*.php"]
severity_min: "high"
languages: ["php"]
buckets: ["error-handling"]
enabled: true
---

@kody-sync

## Instructions

When reviewing extracted PHP services under `include/Services/` or facade patches (for example `include/class.sla.php`):

- Reject facade patches that change the entry-point method signature.
- Reject patches that drop existing side effects or facade-level orchestration (globals, schedule resolution, caching, caller-visible contracts).
- Reject extracted services that call back into the facade entry point they replace (infinite recursion).
- The service must implement lifted logic or forward delegation only. Callers of the facade must keep working the same way.

## Examples

### Bad example
```php
// Facade signature changed, and the service calls back into the facade.
public function addGracePeriod($start, $hours = null) { /* extra required arg */ }
class SlaGracePeriodCalculator {
    public function calculate($start, $hours) {
        return SLA::addGracePeriod($start, $hours);
    }
}
```

### Good example
```php
// Facade keeps its signature and delegates; the service never calls the facade.
public function addGracePeriod($start, $hours = null) {
    return SlaGracePeriodCalculator::calculate($this, $start, $hours);
}
```
