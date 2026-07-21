<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient-check seam. include/class.sla.php's
    SLA::isTransient() is a self-contained inline expression with no
    sub-delegation, so this service lifts that expression verbatim
    (pattern B: inline expression lift) rather than delegating to
    another class/method.

    `$this->flags & self::FLAG_TRANSIENT` uses the bitwise AND (&)
    against SLA::FLAG_TRANSIENT (0x0008), with no strict-equality
    normalization: the result stays the raw int (0 or 8) that PHP's
    truthy/falsy coercion relies on at both call sites. Do not
    substitute $sla->hasFlag() or $sla->get('flags', 0) here — those
    are different accessors used elsewhere in the class — and do not
    touch FLAG_ACTIVE (0x0001), FLAG_ESCALATE (0x0002), or
    FLAG_NOALERTS (0x0004) handling.

    This service reads $sla->flags directly on the passed-in instance
    and never calls back into SLA::isTransient() (the entry-point
    method it replaces), to avoid recursion once the facade delegates
    to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of SLA::isTransient()'s body. Preserves the bitwise
     * AND (&) verbatim — yields SLA::FLAG_TRANSIENT (8) when the bit
     * is set in $sla->flags, or 0 when it is clear. Not normalized to
     * a strict bool.
     */
    public function isTransient(SLA $sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}

?>
