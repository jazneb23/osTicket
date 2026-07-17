<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Lifts the legacy `$this->flags && self::FLAG_ESCALATE` expression from
    SLA::priorityEscalation(). Flag loading and facade concerns stay with
    the caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    /**
     * Evaluate the legacy priority-escalation expression for the bound SLA.
     *
     * Lifts the legacy `$this->flags && self::FLAG_ESCALATE` expression from
     * SLA::priorityEscalation(). Note this is a logical AND (`&&`), not a
     * bitwise AND (`&`) — since SLA::FLAG_ESCALATE is always truthy, this is
     * behaviorally equivalent to `(bool)$sla->flags`, true whenever ANY flag
     * bit is set and false only when flags === 0. This is a pre-existing bug
     * preserved for strangler parity; do not "fix" it to bitwise AND. Does
     * not call priorityEscalation() — the strangler stage routes the facade
     * method here.
     *
     * @param SLA $sla Hydrated SLA instance with flags already loaded
     * @return bool Result of the legacy `$sla->flags && self::FLAG_ESCALATE` expression
     */
    public function resolve($sla) {
        return $sla->flags && SLA::FLAG_ESCALATE;
    }
}

?>
