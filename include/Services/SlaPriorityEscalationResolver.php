<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted core logic for SLA::priorityEscalation(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    NOTE: this intentionally preserves the pre-existing bug in
    SLA::priorityEscalation(): it uses logical `&&`, not bitwise `&`,
    against self::FLAG_ESCALATE. Since FLAG_ESCALATE is a nonzero
    constant, the expression really just tests "is flags nonzero" and
    not "is the ESCALATE bit set". Do NOT correct this to bitwise `&`
    here — sibling methods getInfo(), isActive(), sendAlerts(), and
    hasFlag() on SLA already do the bitwise check correctly, and this
    resolver must not converge with them.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    // Mirrors `return $this->flags && self::FLAG_ESCALATE;` from
    // SLA::priorityEscalation() exactly, including the logical-AND bug.
    // $flags is the SLA instance's own flags bitmask (SLA::$flags);
    // $escalateFlag is SLA::FLAG_ESCALATE, passed in by the caller so
    // this service does not duplicate the magic 0x0002 constant.
    function resolve($flags, $escalateFlag) {
        return $flags && $escalateFlag;
    }
}

?>
