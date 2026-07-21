<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 send-alerts seam. include/class.sla.php's
    SLA::sendAlerts() is a self-contained inline expression with no
    sub-delegation, so this service lifts that expression verbatim
    (pattern B: inline expression lift) rather than delegating to
    another class/method.

    `0 === ($sla->flags & SLA::FLAG_NOALERTS)` uses the bitwise AND
    (&) against SLA::FLAG_NOALERTS (0x0004), compared with strict
    equality (===) so the result is always a real boolean, never a
    truthy/falsy int. Do not substitute $sla->hasFlag() or
    $sla->get('flags', 0) here — those are different accessors used
    elsewhere in the class — and do not conflate SLA::FLAG_NOALERTS
    with the unrelated Team::FLAG_NOALERTS (0x0002) in
    include/class.team.php.

    This service reads $sla->flags directly on the passed-in instance
    and never calls back into SLA::sendAlerts() nor
    SLA::alertOnOverdue() (the entry-point method it replaces and its
    sole caller), to avoid recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Exact lift of SLA::sendAlerts()'s body. Preserves the bitwise
     * AND (&) and strict equality (===) verbatim — true when the
     * FLAG_NOALERTS bit is clear in $sla->flags, false when it is
     * set.
     */
    public function sendAlerts(SLA $sla) {
        return 0 === ($sla->flags & SLA::FLAG_NOALERTS);
    }
}

?>
