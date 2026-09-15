<?php
/*********************************************************************
    SlaPriorityEscalationResolver.php

    Extracted MOD-26 priority-escalation seam. The facade entry point,
    SLA::priorityEscalation() in include/class.sla.php, is a
    self-contained inline expression with no sub-delegation, so this
    service lifts that expression verbatim (Pattern B: inline
    expression lift) rather than delegating to another method.

    This is a pre-existing bug in the legacy code: the original body
    uses PHP's logical AND (&&) instead of bitwise AND (&). Because
    SLA::FLAG_ESCALATE (2) is itself always truthy as a scalar operand,
    `$sla->flags && SLA::FLAG_ESCALATE` reduces to "is $sla->flags
    non-zero", NOT "is the FLAG_ESCALATE bit set in $sla->flags". That
    diverges from the correct bitwise pattern used elsewhere in SLA for
    the same field (getInfo()'s enable_priority_escalation key,
    hasFlag()) — but behavioral parity requires preserving today's
    (buggy) truth table exactly, bug-for-bug.

    This never calls back into SLA::priorityEscalation() (anti-recursion);
    it only reads the passed-in SLA instance's ->flags property directly
    and references the SLA::FLAG_ESCALATE class constant. No DB access,
    global reads, caching, or other side effects are introduced beyond
    the plain property read the original method already performed.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaPriorityEscalationResolver {

    /**
     * Exact lift of the body of SLA::priorityEscalation().
     *
     * Preserves the `&&` (logical AND) operator bug-for-bug — do NOT
     * "fix" this to `&` (bitwise AND), even though that is the correct
     * operator for the same semantic purpose elsewhere in SLA (getInfo(),
     * hasFlag()). Because SLA::FLAG_ESCALATE is a non-zero literal and
     * therefore always truthy, the effective contract is: true whenever
     * $sla->flags is any non-zero value, false only when $sla->flags is
     * exactly 0.
     *
     * @param SLA $sla
     *   An already-loaded SLA instance. Its ->flags property is read
     *   directly (implicit input, already hydrated by the ORM) — this
     *   method performs no lookup or load of its own.
     *
     * @return mixed
     *   The raw result of `$sla->flags && SLA::FLAG_ESCALATE` — PHP's
     *   loose boolean-context value of a logical AND of two operands,
     *   not a bitwise-masked integer.
     */
    public function priorityEscalation(SLA $sla) {
        return $sla->flags && SLA::FLAG_ESCALATE;
    }
}

?>
