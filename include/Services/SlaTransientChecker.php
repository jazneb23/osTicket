<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 seam. The facade method SLA::isTransient()
    in include/class.sla.php delegates here.

    This is an inline-expression lift: the entry-point method body is a
    single self-contained expression with no sub-delegation, so the
    exact expression (including its bitwise & operator) is lifted
    verbatim rather than reimplemented. This service reads the passed
    SLA instance's already-hydrated `flags` property directly — it
    never calls back into SLA::isTransient(), to avoid recursion.

    Preserves the bitwise & operator against SLA::FLAG_TRANSIENT
    (0x0008), consistent with every sibling flag accessor on SLA
    (isActive(), getInfo()'s inline flag checks) and distinct from the
    intentional logical-&& bug preserved in SlaEscalationService for a
    different seam.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of SLA::isTransient(). Reads $sla->flags directly
     * and preserves the bitwise & operator against
     * SLA::FLAG_TRANSIENT (0x0008) verbatim, returning the raw int
     * result (0 or 0x0008) rather than a coerced strict bool.
     */
    public function isTransient(SLA $sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}

?>
