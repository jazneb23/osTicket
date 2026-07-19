<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted core logic for SLA::sendAlerts(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    // Mirrors `return 0 === ($this->flags & self::FLAG_NOALERTS);` from
    // SLA::sendAlerts() exactly. $flags is the SLA instance's own flags
    // bitmask (SLA::$flags); $noAlertsFlag is SLA::FLAG_NOALERTS, passed
    // in by the caller so this service does not duplicate the magic
    // 0x0004 constant.
    function check($flags, $noAlertsFlag) {
        return 0 === ($flags & $noAlertsFlag);
    }
}

?>
