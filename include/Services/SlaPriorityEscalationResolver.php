<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted MOD-26 priority-escalation seam. include/class.sla.php's
    SLA::priorityEscalation() delegates here once the strangler stage
    patches the facade.

    coreLogic for this seam IS the entry-point method body itself: a
    bare inline expression `$this->flags && self::FLAG_ESCALATE` with
    no deeper delegation chain. Per the manifest, this is a pure
    "Lifted logic" pattern (pattern B), so this service simply lifts
    that exact expression verbatim, including the logical-AND operator.

    This is a pre-existing bug relative to every sibling flag-check in
    SLA (isActive(), isTransient(), sendAlerts(), hasFlag(), and even
    getInfo()'s own FLAG_ESCALATE check), all of which use bitwise `&`.
    Because self::FLAG_ESCALATE (2) is non-zero, `$flags && 2` is true
    for ANY non-zero $flags, not just when the escalate bit is set.
    Parity requires reproducing this exact boolean contract bug-for-bug
    -- do NOT "correct" this to bitwise `&`, and do not reimplement or
    otherwise reintroduce any bitmasking logic here.

    This service performs a plain in-memory property read with no
    database access, no global state reads, no lazy-loading of related
    objects, and no translation/localization calls. It never calls
    back into SLA::priorityEscalation() itself, to avoid recursion once
    the facade delegates to it. SLA::getInfo() and SLA::update(), which
    share the flags field and FLAG_ESCALATE constant but implement
    separate, correct bitwise logic, are untouched and out of scope.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    /**
     * Exact lift of the core body of SLA::priorityEscalation():
     * `$this->flags && self::FLAG_ESCALATE`.
     *
     * Preserves the legacy logical-AND (`&&`) operator verbatim, not
     * bitwise `&` -- this is intentionally bug-for-bug compatible with
     * the legacy facade method, not a corrected bitmask check. Returns
     * a PHP boolean scalar, not an integer bitmask value.
     *
     * @param SLA $sla the owning SLA instance (read-only access to its
     *        already-hydrated flags property)
     * @return bool false only when $sla->flags is falsy (0, null, or
     *        unset); true for ANY non-zero flags value, regardless of
     *        whether the FLAG_ESCALATE bit is actually set among them
     */
    public function priorityEscalation(SLA $sla) {
        return $sla->flags && SLA::FLAG_ESCALATE;
    }
}

?>
