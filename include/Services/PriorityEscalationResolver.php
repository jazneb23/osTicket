<?php
/*********************************************************************
    PriorityEscalationResolver.php

    Extracted MOD-26 priority-escalation seam. The facade method
    SLA::priorityEscalation() in include/class.sla.php (lines 116-118)
    delegates here for its entire body.

    This service never calls back into SLA::priorityEscalation() (the
    entry-point method it replaces) — it is an inline expression lift:
    the exact `$this->flags && self::FLAG_ESCALATE` expression from the
    original method is reproduced here, operating on the passed-in SLA
    instance's ->flags property and the SLA::FLAG_ESCALATE constant
    directly.

    NOTE: This intentionally preserves the logical AND (&&) used by the
    original method, NOT the bitwise AND (&) used by the sibling
    accessor SLA::getInfo() for the same bit. Because SLA::FLAG_ESCALATE
    (0x0002) is a nonzero literal, `$flags && SLA::FLAG_ESCALATE` reduces
    to the boolean cast of $flags: true whenever ANY flag bit is set
    (FLAG_ACTIVE, FLAG_ESCALATE, FLAG_NOALERTS, and/or FLAG_TRANSIENT),
    and false only when $flags === 0. This is almost certainly a
    copy/paste typo for the bitwise operator in the original, but it is
    the seam's real, currently-shipping behavior and strangler parity
    forbids "fixing" it here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class PriorityEscalationResolver {

    /**
     * Exact lift of SLA::priorityEscalation()'s body:
     *
     *   return $this->flags && self::FLAG_ESCALATE;
     *
     * Reads the already in-memory $sla->flags property (no ORM lookups,
     * no lazy-loading, no global state) and reproduces the logical AND
     * (not bitwise AND) against SLA::FLAG_ESCALATE, exactly as the
     * original expression does. Returns a strict PHP boolean: true
     * whenever $sla->flags is any nonzero integer, false only when
     * $sla->flags === 0. Pure query, no mutation, no side effects.
     */
    public function priorityEscalation(SLA $sla) {
        return $sla->flags && SLA::FLAG_ESCALATE;
    }
}

?>
