<?php
/*********************************************************************
    SlaPriorityEscalationService.php

    Extracted MOD-26 priority-escalation seam. SLA::priorityEscalation()
    in include/class.sla.php delegates here for its entire body, which
    is a single inline expression with no sub-delegation.

    This is an Inline Expression Lift: the service reproduces the
    exact `$this->flags && self::FLAG_ESCALATE` expression verbatim
    (logical &&, not bitwise &), operating on a passed-in flags value
    rather than calling back into SLA::priorityEscalation(), to avoid
    recursion once the facade delegates to it.

    Note: this preserves the legacy method's existing behavior, which
    diverges from every sibling flag-check method in SLA (isActive(),
    isTransient(), sendAlerts(), hasFlag(), getInfo()) in that it does
    not isolate the FLAG_ESCALATE bit -- it collapses to a truthiness
    check on the whole flags bitmask. That quirk is intentionally
    preserved here, not corrected.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationService {

    /**
     * Exact lift of the core expression from SLA::priorityEscalation().
     * Takes the already-hydrated flags bitmask (SLA::$flags) and
     * returns the plain boolean result of `$flags && SLA::FLAG_ESCALATE`.
     *
     * Deliberately uses logical && (not bitwise &), matching the
     * currently-shipping (buggy) legacy behavior: true for any nonzero
     * $flags, regardless of which bit(s) are set; false only when
     * $flags === 0. This must not be "fixed" to bitwise & here.
     */
    public function priorityEscalation($flags) {
        return $flags && SLA::FLAG_ESCALATE;
    }
}

?>
