<?php
/*********************************************************************
    SlaTransientChecker.php

    Delegates SLA transient-flag evaluation to the existing
    SLA::isTransient() expression. Flag loading and facade concerns
    stay with the caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Evaluate whether the transient flag is set for the bound SLA.
     *
     * Lifts the legacy `$this->flags & self::FLAG_TRANSIENT` expression from
     * SLA::isTransient(). Does not call isTransient() — the strangler stage
     * routes the facade method here.
     *
     * @param SLA $sla Hydrated SLA instance with flags already loaded
     * @return int|bool Raw expression result (truthy/falsy contract)
     */
    public function isTransient($sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}

?>
