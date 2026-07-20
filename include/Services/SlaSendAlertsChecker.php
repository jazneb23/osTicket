<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 send-alerts seam. SLA::sendAlerts() in
    include/class.sla.php delegates here for its entire body, which
    is a single inline expression with no sub-delegation.

    This is an Inline Expression Lift: the service reproduces the
    exact `0 === ($this->flags & self::FLAG_NOALERTS)` expression
    verbatim (strict === comparison against a bitwise AND, not loose
    truthiness), operating on a passed-in flags value rather than
    calling back into SLA::sendAlerts() or SLA::alertOnOverdue(), to
    avoid recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Exact lift of the core expression from SLA::sendAlerts().
     * Takes the already-hydrated flags bitmask (SLA::$flags) and
     * returns the plain boolean result of
     * `0 === ($flags & SLA::FLAG_NOALERTS)`.
     *
     * Uses strict === comparison against 0, not truthiness: true only
     * when the FLAG_NOALERTS (0x0004) bit is completely clear, false
     * whenever any bit of FLAG_NOALERTS is set, regardless of the
     * state of any other flag bits.
     */
    public function sendAlerts($flags) {
        return 0 === ($flags & SLA::FLAG_NOALERTS);
    }
}

?>
