<?php

/**
 * Extracted core logic for SLA::isTransient() (include/class.sla.php).
 *
 * Lifts the exact `$this->flags & self::FLAG_TRANSIENT` expression out of
 * the facade method body. This class must never call back into
 * SLA::isTransient() itself.
 */
class SlaTransientChecker {

    /**
     * @param SLA $sla The SLA instance whose `flags` bitmask is checked.
     * @return int Raw bitwise-AND result: 0 when FLAG_TRANSIENT is unset,
     *              or 8 (0x0008) when set. Not cast to bool.
     */
    static function isTransient($sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}
