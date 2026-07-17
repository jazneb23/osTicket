<?php
/*********************************************************************
    Services/SlaTransientChecker.php

    Extracted seam for SLA::isTransient() (ticket MOD-29).

    Lifts the bitmask expression `$this->flags & self::FLAG_TRANSIENT`
    out of include/class.sla.php so the facade method can delegate to
    it. This is a pure, side-effect-free computation against the flags
    already present on the passed SLA instance.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Mirrors SLA::isTransient(): `$this->flags & self::FLAG_TRANSIENT`.
     *
     * Reads the flags bitmask from the passed SLA instance (never calls
     * back into $sla->isTransient()) and returns the raw bitwise-AND
     * result — an int (0 or SLA::FLAG_TRANSIENT), not a coerced bool —
     * so callers relying on truthiness see identical behavior.
     */
    static function isTransient(SLA $sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}
