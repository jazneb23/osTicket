<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 send-alerts seam. include/class.sla.php's
    SLA::sendAlerts() delegates here once the strangler stage patches
    the facade.

    coreLogic for this seam IS the entry-point method body itself: a
    bare inline expression `0 === ($this->flags & self::FLAG_NOALERTS)`
    with no deeper delegation chain. Per the manifest, this is a pure
    "Lifted logic" pattern (pattern B), so this service simply lifts
    that exact expression verbatim, including the bitwise-AND operator
    and the strict `===` comparison.

    Unlike SlaPriorityEscalationResolver's intentionally-preserved
    `&&` bug for FLAG_ESCALATE, sendAlerts() has no such bug -- it
    already uses correct bitwise `&` and strict `===` identity. Do
    NOT "fix" or otherwise change these operators; reproduce them
    exactly.

    This service performs a plain in-memory property read with no
    database access, no global state reads, no lazy-loading of
    related objects, and no translation/localization calls. It never
    calls back into SLA::sendAlerts() or SLA::alertOnOverdue(), to
    avoid recursion once the facade delegates to it.
    SLA::alertOnOverdue() itself is left untouched and continues to
    delegate to sendAlerts()'s new implementation transitively.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Exact lift of the core body of SLA::sendAlerts():
     * `0 === ($this->flags & self::FLAG_NOALERTS)`.
     *
     * Preserves the legacy bitwise-AND (`&`) operator and strict
     * identity (`===`) comparison verbatim -- this is a faithful,
     * bug-for-bug (i.e. bug-free) port of the legacy facade method,
     * not a reimplementation with different operators or guards.
     *
     * @param SLA $sla the owning SLA instance (read-only access to its
     *        already-hydrated flags property)
     * @return bool true when the FLAG_NOALERTS bit is NOT set in
     *        $sla->flags (alerts enabled); false when that bit IS set
     *        (alerts disabled)
     */
    public function sendAlerts(SLA $sla) {
        return 0 === ($sla->flags & SLA::FLAG_NOALERTS);
    }
}

?>
