<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted priority-escalation check for SLA::priorityEscalation()
    (include/class.sla.php). Accepts the raw SLA flags bitmask plus the
    FLAG_ESCALATE constant and reproduces the exact (logical-AND, not
    bitwise-AND) expression from the entry point verbatim. This is an
    inline-expression lift: no schedule resolution, no global $cfg
    reads, and no other caller-specific orchestration belong here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    // Mirrors the core logic of SLA::priorityEscalation() exactly,
    // including its logical-AND (not bitwise-AND) operator against
    // FLAG_ESCALATE. FLAG_ESCALATE is nonzero and therefore always
    // truthy under `&&`, so this returns true whenever $flags is any
    // nonzero value, and false only when $flags === 0.
    static function priorityEscalation($flags, $flagEscalate) {
        return $flags && $flagEscalate;
    }
}
