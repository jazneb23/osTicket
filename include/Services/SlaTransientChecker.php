<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 is-transient seam. SLA::isTransient() in
    include/class.sla.php delegates here for its entire body, which
    is a single inline expression with no sub-delegation.

    This is an Inline Expression Lift: the service reproduces the
    exact `$this->flags & self::FLAG_TRANSIENT` expression verbatim
    (a plain bitwise AND, not a strict boolean cast or !== 0
    comparison), operating on a passed-in flags value rather than
    calling back into SLA::isTransient(), to avoid recursion once
    the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of the core expression from SLA::isTransient().
     * Takes the already-hydrated flags bitmask (SLA::$flags) and
     * returns the plain bitwise-AND result of
     * `$flags & SLA::FLAG_TRANSIENT`.
     *
     * Callers only rely on truthiness (0 when the FLAG_TRANSIENT
     * (0x0008) bit is clear, nonzero when it is set), so the exact
     * int return type/value is preserved rather than casting to a
     * strict bool.
     */
    public function isTransient($flags) {
        return $flags & SLA::FLAG_TRANSIENT;
    }
}

?>
