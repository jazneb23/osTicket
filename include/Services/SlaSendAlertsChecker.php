<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 seam. The facade method SLA::sendAlerts()
    in include/class.sla.php delegates here. SLA::alertOnOverdue()
    remains a same-class pass-through to SLA::sendAlerts() and is
    untouched by this extraction.

    This is an inline-expression lift: the entry-point method body is a
    single self-contained expression with no sub-delegation, so the
    exact expression (including its strict === comparison and bitwise
    & operator) is lifted verbatim rather than reimplemented. This
    service reads the passed SLA instance's already-hydrated `flags`
    property directly — it never calls back into SLA::sendAlerts() or
    SLA::alertOnOverdue(), to avoid recursion.

    Preserves the strict identity comparison `0 === (...)` rather than
    a loose `== 0` or truthy check (contrast with Team::alertsEnabled(),
    which uses `== 0` on a different constant/class, and with the
    intentional-bug precedent in SlaEscalationService showing that
    bitwise-vs-logical operator distinctions are load-bearing for
    parity in this codebase).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Exact lift of SLA::sendAlerts(). Reads $sla->flags directly
     * and preserves the strict === comparison against 0 and the
     * bitwise & operator against SLA::FLAG_NOALERTS (0x0004)
     * verbatim.
     */
    public function sendAlerts(SLA $sla) {
        return 0 === ($sla->flags & SLA::FLAG_NOALERTS);
    }
}

?>
