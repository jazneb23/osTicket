<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted MOD-26 priority-escalation seam. The facade method
    SLA::priorityEscalation() in include/class.sla.php (lines 117-119)
    delegates here for its entire body — the method is a single inline
    expression with no sub-delegation, so this is an "inline expression
    lift" (pattern B), not a sub-method delegation.

    CRITICAL QUIRK PRESERVED VERBATIM: the original method uses PHP's
    logical `&&` operator, NOT the bitwise `&` operator used elsewhere
    in SLA for flag checks (isActive(), sendAlerts(), getInfo()).
    Because `&&` always yields a strict bool and FLAG_ESCALATE (2) is
    itself always truthy, this is equivalent to `(bool) $flags` — it
    returns true whenever ANY flag bit is set (not necessarily the
    FLAG_ESCALATE bit specifically), and false only when flags is
    exactly 0. This is a pre-existing discrepancy versus the bitwise
    checks elsewhere in the class and must be reproduced exactly, not
    "corrected" to bitwise AND.

    Facade concerns that stay in include/class.sla.php (per the seam
    manifest, this class must NOT re-implement them):
      - Nothing else — the entry point's body is exactly this
        expression, with no schedule resolution, no global $cfg reads,
        and no caller-specific orchestration to strip out.
      - SLA::getInfo() and SLA::update(), the two sibling accessors
        that read/write the same flags bit via correct bitwise AND,
        are separate facade-level concerns and are out of scope for
        this seam; this class must not call or reimplement either.

    This class never calls back into SLA::priorityEscalation() itself;
    it operates on the raw $flags value passed in by the facade, so
    there is no recursion risk once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    /**
     * Exact lift of the body of SLA::priorityEscalation().
     *
     * @param int $flags
     *   The SLA's raw flags bitmask (ost_sla.flags), already loaded by
     *   the ORM off the SLA instance (e.g. $sla->flags) — no additional
     *   DB query or lazy load happens here or in the caller.
     *
     * @return bool
     *   Strict bool (guaranteed by PHP's `&&` operator). True whenever
     *   $flags is non-zero — i.e. any bit is set, not necessarily the
     *   FLAG_ESCALATE bit specifically (see the logical-AND quirk
     *   documented above). False only when $flags === 0.
     */
    public function resolve($flags) {
        return $flags && SLA::FLAG_ESCALATE;
    }
}

?>
