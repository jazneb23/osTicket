<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient-check seam. The facade entry point,
    SLA::isTransient() in include/class.sla.php, has no orchestration
    concerns to keep — it is a self-contained inline expression over
    the SLA instance's own $flags property. This service lifts that
    exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_TRANSIENT
    constant value, so SLA::FLAG_TRANSIENT remains the single source
    of truth for the bitmask and is not redefined here.

    Deliberately preserved quirk: the original body returns the raw
    result of the bitwise AND (`$this->flags & self::FLAG_TRANSIENT`)
    rather than a strict bool — 0 when the bit is unset, or a non-zero
    int when it is set. That must be reproduced as-is, not cast to
    bool or normalized to the `!= 0` idiom used by hasFlag().

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into SLA::isTransient(), to avoid recursion once the
    facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Core lift of the body of SLA::isTransient().
     *
     * @param int $flags
     *      The SLA instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $transientFlag
     *      The FLAG_TRANSIENT bitmask value (facade's
     *      self::FLAG_TRANSIENT), passed in so SLA remains the single
     *      source of truth for the constant.
     *
     * @return int
     *      The raw result of $flags & $transientFlag: 0 (falsy) when
     *      the bit is unset, or a non-zero int (truthy) when it is
     *      set. Not cast to bool.
     */
    public function isTransient($flags, $transientFlag) {
        return $flags & $transientFlag;
    }
}

?>
