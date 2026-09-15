<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient seam. SLA::isTransient() in
    include/class.sla.php is a self-contained inline expression with
    no sub-delegation, so per the "inline expression lift" pattern this
    service lifts that exact expression rather than delegating to any
    sub-method.

    This service accepts the raw `flags` int directly (not the SLA
    object) and never calls back into SLA::isTransient() (the
    entry-point method it replaces) or any other SLA instance method,
    to avoid recursion once the facade delegates to it. All facade
    concerns (there are none here beyond reading $this->flags, which
    the caller passes in) remain in include/class.sla.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of the core logic in SLA::isTransient():
     * `$flags & SLA::FLAG_TRANSIENT`.
     *
     * This is a bitwise AND against SLA::FLAG_TRANSIENT (0x0008) only.
     * SLA::FLAG_ACTIVE (0x0001), SLA::FLAG_ESCALATE (0x0002), and
     * SLA::FLAG_NOALERTS (0x0004) are never read here and have zero
     * effect on the result. The return value is the raw int result of
     * the bitwise AND (0 or 8) — NOT normalized to a strict boolean —
     * matching the SLA::isActive()/getInfo() precedent, and distinct
     * from the `!!(...)` pattern used by DepartmentActiveChecker
     * (MOD-32). This exact non-bool-coerced return value must be
     * preserved because callers only use it in loose truthy/falsy
     * `if`/`||`/`&&` contexts.
     *
     * @param int $flags
     *   The raw, already-loaded SLA::flags packed bitmask value (the
     *   caller must pass $this->flags from the SLA facade; this
     *   service performs no DB read, lazy-load, or global $cfg read
     *   of its own).
     *
     * @return int
     *   0 or 8: the raw result of $flags & SLA::FLAG_TRANSIENT.
     */
    public function isTransient($flags) {
        return $flags & SLA::FLAG_TRANSIENT;
    }
}

?>
