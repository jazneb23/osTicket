<?php
/*********************************************************************
    SlaSendAlertsChecker.php

    Extracted MOD-28 send-alerts seam. SLA::sendAlerts()
    (include/class.sla.php) delegates here for its core (single
    expression) logic.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the expression
    `0 === ($this->flags & self::FLAG_NOALERTS)`, with no sub-delegation
    to any other class or method. That exact expression — the strict
    `0 ===` identity check against the bitwise `&` of flags and
    FLAG_NOALERTS — is lifted verbatim here. Unlike its sibling
    SLA::priorityEscalation() (MOD-26, which has a pre-existing `&&`
    vs `&` bug), this method's existing semantics are already correct
    and are preserved as-is, with no operator substitution (e.g. no
    rewriting to `!($flags & FLAG_NOALERTS)`).

    Facade-level concerns stay in include/class.sla.php: this service
    never re-fetches or hydrates the SLA row, reads no global state, and
    never calls back into SLA::sendAlerts() or SLA::alertOnOverdue()
    (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaSendAlertsChecker {

    /**
     * Exact lift of SLA::sendAlerts()'s core expression.
     *
     * @param int $flags   The already-hydrated SLA.flags bitmask
     *                      (i.e. $sla->flags). No DB read/re-fetch is
     *                      performed here.
     * @param int $noAlertsFlag   SLA::FLAG_NOALERTS, passed in by the
     *                      caller so this service never hardcodes the
     *                      literal 0x0004.
     *
     * @return bool   True iff `$flags & $noAlertsFlag` is exactly 0
     *                 (FLAG_NOALERTS bit clear — overdue alerts
     *                 enabled). False iff that bitwise AND is
     *                 non-zero, i.e. FLAG_NOALERTS is set (overdue
     *                 alerts disabled), regardless of which other
     *                 bits are also set.
     */
    public function isSendAlertsEnabled($flags, $noAlertsFlag) {
        return 0 === ($flags & $noAlertsFlag);
    }
}

?>
