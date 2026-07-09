<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Pure wrapper around the legacy SLA::priorityEscalation() flag
    expression. Flag reads stay in the caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    const FLAG_ESCALATE = 0x0002;

    /**
     * Evaluate priority escalation from in-memory SLA flags.
     *
     * Preserves legacy logical-AND semantics from SLA::priorityEscalation():
     * $flags && FLAG_ESCALATE (not bitwise AND, not hasFlag()).
     */
    public function resolve($flags) {
        return $flags && self::FLAG_ESCALATE;
    }
}

?>
