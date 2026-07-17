<?php
/**
 * SlaTransientChecker
 *
 * Extracted core logic for SLA::isTransient() (include/class.sla.php).
 * Tests the FLAG_TRANSIENT bit (0x0008) against a raw SLA flags bitmask.
 */
class SlaTransientChecker {

    const FLAG_TRANSIENT = 0x0008;

    /**
     * @param int $flags Raw SLA flags bitmask (ost_sla.flags), read live
     *                    from the caller at call time.
     * @return int The bitwise-AND result: 0 when FLAG_TRANSIENT is unset,
     *             8 when set. Truthiness matches SLA::isTransient()'s
     *             original return value exactly.
     */
    function isTransient($flags) {
        return $flags & self::FLAG_TRANSIENT;
    }
}
