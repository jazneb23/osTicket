<?php
/**
 * SlaSendAlertsChecker
 *
 * Extracted core logic for SLA::sendAlerts() (include/class.sla.php).
 * Pure function of an SLA row's `flags` bitmask — no DB access, no
 * SLA::lookup() calls, and no global state. Callers must hydrate `flags`
 * (e.g. via the ORM) before invoking this checker.
 */
class SlaSendAlertsChecker {

    /**
     * Determine whether overdue-alert emails should be sent for an SLA,
     * based on whether the FLAG_NOALERTS bit is set on its flags bitmask.
     *
     * @param int $flags          The SLA's raw `flags` bitmask.
     * @param int $noAlertsFlag   The FLAG_NOALERTS bit value (SLA::FLAG_NOALERTS).
     *
     * @return bool Strict boolean — true when FLAG_NOALERTS is NOT set
     *              (alerts should be sent), false when it is set.
     */
    static function shouldSendAlerts($flags, $noAlertsFlag) {
        return 0 === ($flags & $noAlertsFlag);
    }
}
