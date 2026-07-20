<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 send-alerts seam. The facade entry point,
    SLA::sendAlerts() in include/class.sla.php, has no orchestration
    concerns to keep — it is a self-contained inline expression over
    the SLA instance's own $flags property. This service lifts that
    exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_NOALERTS
    constant value, so SLA::FLAG_NOALERTS remains the single source of
    truth for the bitmask and is not redefined here.

    Deliberately preserved quirk: the original body uses a strict
    identity check (`0 === ($flags & $noAlertsFlag)`), unlike the raw
    truthy bitwise accessors elsewhere in the same class (isActive(),
    isTransient(), getInfo()). That strictness must be reproduced as-is,
    not loosened or normalized to match those siblings.

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into SLA::sendAlerts() or SLA::alertOnOverdue(), to
    avoid recursion once the facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Core lift of the body of SLA::sendAlerts().
     *
     * @param int $flags
     *      The SLA instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $noAlertsFlag
     *      The FLAG_NOALERTS bitmask value (facade's
     *      self::FLAG_NOALERTS), passed in so SLA remains the single
     *      source of truth for the constant.
     *
     * @return bool
     *      True when the FLAG_NOALERTS bit is NOT set in $flags
     *      (overdue alerts enabled); false when that bit IS set
     *      (overdue alerts disabled).
     */
    public function sendAlerts($flags, $noAlertsFlag) {
        return 0 === ($flags & $noAlertsFlag);
    }
}

?>
