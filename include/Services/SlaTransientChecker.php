<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient-flag seam. The facade entry point,
    SLA::isTransient() in include/class.sla.php, is a self-contained
    inline expression with no sub-delegation:

        return $this->flags & self::FLAG_TRANSIENT;

    so this service lifts that expression verbatim (Pattern B: inline
    expression lift) rather than delegating to another method.

    This never calls back into SLA::isTransient() (anti-recursion); it
    only reads the passed-in SLA instance's ->flags property directly
    and references the SLA::FLAG_TRANSIENT class constant (0x0008), so
    the flag value can never drift out of sync with the facade. No DB
    access, global reads, caching, or other side effects are introduced
    beyond the plain property read the original method already
    performed.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of the body of SLA::isTransient().
     *
     * Preserves the bitwise `&` operator and the raw int return value
     * (0 or 8) — do NOT coerce this to a real boolean. No known caller
     * depends on the specific int today, but the smallest-patch rule
     * means the literal return value type/shape must not change.
     *
     * @param SLA $sla
     *   An already-loaded SLA instance. Its ->flags property is read
     *   directly (implicit input, already hydrated by the ORM) — this
     *   method performs no lookup or load of its own.
     *
     * @return int
     *   The raw result of `$sla->flags & SLA::FLAG_TRANSIENT`: 8 when
     *   the TRANSIENT bit is set, 0 otherwise.
     */
    public function isTransient(SLA $sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}
?>
