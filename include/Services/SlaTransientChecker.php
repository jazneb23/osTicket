<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted core logic for SLA::isTransient(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    // Mirrors `return $this->flags & self::FLAG_TRANSIENT;` from
    // SLA::isTransient() exactly. $flags is the SLA instance's own flags
    // bitmask (SLA::$flags); $transientFlag is SLA::FLAG_TRANSIENT, passed
    // in by the caller so this service does not duplicate the magic
    // 0x0008 constant. The raw int result is returned as-is (not cast to
    // bool), since callers rely on truthy `if`/`||` semantics.
    function check($flags, $transientFlag) {
        return $flags & $transientFlag;
    }
}

?>
