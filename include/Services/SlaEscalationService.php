<?php
/*********************************************************************
    SlaEscalationService.php

    Extracted MOD-26 seam. SLA::priorityEscalation() in
    include/class.sla.php delegates here for its entire body.

    coreLogic for this seam IS the entry-point method body: a single,
    self-contained inline expression with no sub-delegation to any
    other method. Per the "Inline expression lift" pattern, that exact
    expression is lifted verbatim into this service rather than
    delegated to some other implementation.

    Preserved verbatim from include/class.sla.php:117:

        return $this->flags && self::FLAG_ESCALATE;

    This uses PHP's logical AND (&&), NOT the bitwise AND (&) used by
    every sibling flag-check on SLA (getInfo(), isActive(),
    isTransient(), hasFlag()). Because FLAG_ESCALATE (0x0002) is a
    nonzero constant it is always truthy, so this expression reduces to
    (bool) $flags: true whenever ANY flag bit is set, false only when
    flags === 0. This is intentionally NOT corrected to bitwise `&` —
    parity requires bug-for-bug behavioral equivalence with the
    original, not the presumably-intended behavior.

    This service reads only the already-hydrated flags value passed in
    by the caller. It performs no DB read/write, no global $cfg read,
    no lazy-loading, no caching, no file I/O, no Signal::send(), and no
    logging — matching the original method exactly. It never calls back
    into SLA::priorityEscalation() itself, to avoid recursion once the
    facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaEscalationService {

    /**
     * Exact lift of the core expression inside SLA::priorityEscalation().
     *
     * @param int $flags
     *     The already-hydrated SLA::$flags bitmask (read by the facade
     *     from the already-loaded instance — e.g. $sla->flags — before
     *     calling here). This service does not read $this->flags, does
     *     not query the DB, and does not lazy-load anything; it operates
     *     purely in-memory on the value passed in.
     *
     * @return bool
     *     The boolean produced by `$flags && SLA::FLAG_ESCALATE`, using
     *     PHP's logical AND exactly as the original method did. Not the
     *     bitwise-masked int that `$flags & SLA::FLAG_ESCALATE` would
     *     produce elsewhere in this class (e.g. getInfo()'s
     *     enable_priority_escalation).
     */
    public function priorityEscalation($flags) {
        return $flags && SLA::FLAG_ESCALATE;
    }
}

?>
