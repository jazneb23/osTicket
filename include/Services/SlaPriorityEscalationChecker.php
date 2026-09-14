<?php
/*********************************************************************
    SlaPriorityEscalationChecker.php

    Extracted MOD-26 priority-escalation seam. SLA::priorityEscalation()
    (include/class.sla.php) delegates here for its core (single
    expression) logic.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the expression `$this->flags && self::FLAG_ESCALATE`,
    with no sub-delegation to any other class or method. That exact
    expression — including the use of the logical && operator rather
    than the bitwise & used by every sibling flag-check in SLA
    (isActive(), isTransient(), sendAlerts(), getInfo()) — is lifted
    verbatim here.

    NOTE: Because SLA::FLAG_ESCALATE (0x0002) is a non-zero literal, it
    is always truthy in a boolean context, so `&& self::FLAG_ESCALATE`
    is a no-op and this is functionally equivalent to (bool) $flags. That
    means this reports true whenever ANY bit is set in $flags (e.g.
    FLAG_ACTIVE alone, with escalation NOT requested), not only when the
    FLAG_ESCALATE bit specifically is set. This is a pre-existing bug in
    the live facade that must be preserved for parity; do NOT "fix" it by
    switching to bitwise &. flags=0 is the only input where the buggy
    (&&) and the intended (&) semantics agree (both false).

    Facade-level concerns stay in include/class.sla.php: this service
    never re-fetches or hydrates the SLA row, reads no global state, and
    never calls back into SLA::priorityEscalation() (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationChecker {

    /**
     * Exact lift of SLA::priorityEscalation()'s core expression.
     *
     * @param int $flags   The already-hydrated SLA.flags bitmask
     *                      (i.e. $sla->flags). No DB read/re-fetch is
     *                      performed here.
     * @param int $escalateFlag   SLA::FLAG_ESCALATE, passed in by the
     *                      caller so this service never hardcodes the
     *                      literal 2.
     *
     * @return bool   True iff $flags is non-zero (bug-for-bug parity
     *                 with the current facade behavior — NOT limited to
     *                 the FLAG_ESCALATE bit specifically). False iff
     *                 $flags === 0.
     */
    public function isPriorityEscalationEnabled($flags, $escalateFlag) {
        return $flags && $escalateFlag;
    }
}

?>
