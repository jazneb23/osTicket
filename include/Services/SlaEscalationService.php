<?php
/*********************************************************************
    SlaEscalationService.php

    Extracted MOD-26 seam. The facade method SLA::priorityEscalation()
    in include/class.sla.php delegates here.

    This is an inline-expression lift: the entry-point method body is a
    single self-contained expression with no sub-delegation, so the
    exact expression (including its operator) is lifted verbatim rather
    than reimplemented. This service reads the passed SLA instance's
    already-hydrated `flags` property directly — it never calls back
    into SLA::priorityEscalation(), hasFlag(), getInfo(), or any other
    bitwise-& accessor, to avoid recursion and to avoid silently
    "fixing" the operator mismatch documented below.

    *** INTENTIONAL BUG PARITY — DO NOT "FIX" ***
    The original code uses the logical AND operator (&&) instead of the
    bitwise AND operator (&) used by every sibling flag accessor on SLA
    (getInfo(), isActive(), isTransient(), sendAlerts(), hasFlag()).
    Because && short-circuits to a PHP boolean and self::FLAG_ESCALATE
    (0x0002) is always truthy, the expression reduces to
    `((bool) $sla->flags) && true` — it returns true for ANY nonzero
    flags value (not specifically when the FLAG_ESCALATE bit is set),
    and false only when flags is 0/null. This mismatch versus a strict
    bitwise test is preserved verbatim per the strangler pattern.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaEscalationService {

    /**
     * Exact lift of SLA::priorityEscalation(). Reads $sla->flags
     * directly (no hasFlag()/getInfo()/bitwise-& helper) and preserves
     * the logical && operator verbatim, including its mismatch versus
     * a strict FLAG_ESCALATE bit test.
     */
    public function priorityEscalation(SLA $sla) {
        return $sla->flags && SLA::FLAG_ESCALATE;
    }
}

?>
