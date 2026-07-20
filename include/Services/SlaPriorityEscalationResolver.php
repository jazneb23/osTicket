<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted MOD-26 priority-escalation seam. The facade entry point,
    SLA::priorityEscalation() in include/class.sla.php, has no
    orchestration concerns to keep — it is a self-contained inline
    expression over the SLA instance's own $flags property. This
    service lifts that exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_ESCALATE
    constant value, so SLA::FLAG_ESCALATE remains the single source of
    truth for the bitmask and is not redefined here.

    Deliberately preserved quirk: the original body uses the logical
    `&&` operator, not the bitwise `&` used by every sibling flag
    accessor in class.sla.php (getInfo(), isActive(), isTransient(),
    sendAlerts(), hasFlag()). Since FLAG_ESCALATE is a non-zero int,
    this reduces to bool($flags) — true for ANY nonzero flags value,
    not specifically the escalate bit. That is today's legacy
    contract and must be reproduced bug-for-bug, not "fixed" to a
    bitwise check.

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into SLA::priorityEscalation() itself, to avoid
    recursion once the facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    /**
     * Core lift of the body of SLA::priorityEscalation().
     *
     * @param int $flags
     *      The SLA instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $escalateFlag
     *      The FLAG_ESCALATE bitmask value (facade's
     *      self::FLAG_ESCALATE), passed in so SLA remains the single
     *      source of truth for the constant.
     *
     * @return bool
     *      True when $flags is any non-zero value, false when
     *      $flags === 0. Note this does NOT isolate $escalateFlag —
     *      that is the preserved legacy quirk of the `&&` expression.
     */
    public function isPriorityEscalationEnabled($flags, $escalateFlag) {
        return $flags && $escalateFlag;
    }
}

?>
