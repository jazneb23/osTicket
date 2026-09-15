<?php
/*********************************************************************
    SlaPriorityEscalationChecker.php

    Extracted MOD-26 priority-escalation seam. SLA::priorityEscalation()
    in include/class.sla.php is a self-contained inline expression with
    no sub-delegation, so per the "inline expression lift" pattern this
    service lifts that exact expression rather than delegating to any
    sub-method.

    This service accepts the raw `flags` int directly (not the SLA
    object) and never calls back into SLA::priorityEscalation() (the
    entry-point method it replaces) or any other SLA instance method,
    to avoid recursion once the facade delegates to it. All facade
    concerns (there are none here beyond reading $this->flags, which
    the caller passes in) remain in include/class.sla.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationChecker {

    /**
     * Exact lift of the core logic in SLA::priorityEscalation():
     * `$flags && SLA::FLAG_ESCALATE`.
     *
     * NOTE: this is logical `&&`, not bitwise `&`. Because
     * SLA::FLAG_ESCALATE (2) is always truthy, the expression collapses
     * to `(bool) $flags`: it returns true whenever $flags is ANY
     * non-zero value — regardless of which bit(s) are actually set —
     * and returns false only when $flags === 0. This is a pre-existing
     * behavioral quirk/bug (distinct from the correct bitwise check
     * used by SLA::getInfo()'s enable_priority_escalation) that MUST be
     * preserved exactly, not corrected.
     *
     * @param int $flags
     *   The raw, already-loaded SLA::flags packed bitmask value (the
     *   caller must pass $this->flags from the SLA facade; this
     *   service performs no DB read, lazy-load, or global $cfg read
     *   of its own).
     *
     * @return bool
     *   True for any non-zero $flags; false only when $flags === 0.
     */
    public function priorityEscalation($flags) {
        return $flags && SLA::FLAG_ESCALATE;
    }
}

?>
