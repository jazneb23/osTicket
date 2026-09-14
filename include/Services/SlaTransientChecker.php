<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient-flag seam. SLA::isTransient()
    (include/class.sla.php) delegates here for its core (single
    expression) logic.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the expression `$this->flags & self::FLAG_TRANSIENT`,
    with no sub-delegation to any other class or method. That exact
    expression — the bitwise `&` of flags and FLAG_TRANSIENT, with no
    boolean cast — is lifted verbatim here. This method's existing
    semantics are already correct (contrast with the sibling
    SLA::priorityEscalation(), MOD-26, which has a pre-existing `&&`
    vs `&` bug); no operator substitution is introduced here, and the
    `&&` bug from that sibling method must not be copied into this one.

    Facade-level concerns stay in include/class.sla.php: this service
    never re-fetches or hydrates the SLA row, reads no global state,
    and never calls back into SLA::isTransient() (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of SLA::isTransient()'s core expression.
     *
     * @param int $flags          The already-hydrated SLA.flags bitmask
     *                            (i.e. $sla->flags). No DB read/re-fetch
     *                            is performed here.
     * @param int $transientFlag  SLA::FLAG_TRANSIENT, passed in by the
     *                            caller so this service never hardcodes
     *                            the literal 0x0008.
     *
     * @return int   The raw result of `$flags & $transientFlag`
     *                (0 or FLAG_TRANSIENT's value in practice), not
     *                cast to a strict PHP bool. Callers only use this
     *                in boolean contexts, so truthy means the
     *                FLAG_TRANSIENT bit is set and falsy means it is
     *                clear, regardless of which other bits are also
     *                set.
     */
    public function isTransient($flags, $transientFlag) {
        return $flags & $transientFlag;
    }
}

?>
