<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 department-active seam. Dept::isActive()
    (include/class.dept.php) delegates here for its core (single
    expression) logic.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the expression `!!($this->flags & self::FLAG_ACTIVE)`,
    with no sub-delegation to any other class or method. That exact
    expression — including the bitwise & operator (not a normalized
    hasFlag()-style helper) and the double-negation !! that coerces the
    integer result to a genuine PHP boolean — is lifted verbatim here.

    Dept::getStatus() and Dept::allowsReopen() read the same flags
    property with their own inline bitwise tests but are separate
    facade methods not covered by this seam; they are not reimplemented
    or routed through this service.

    Facade-level concerns stay in include/class.dept.php: this service
    never re-fetches or hydrates the Dept row, reads no global state,
    and never calls back into Dept::isActive() (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of Dept::isActive()'s core expression.
     *
     * @param int $flags       The already-hydrated Dept.flags bitmask
     *                         (i.e. $dept->flags). No DB read/re-fetch
     *                         is performed here.
     * @param int $activeFlag  Dept::FLAG_ACTIVE, passed in by the
     *                         caller so this service never hardcodes
     *                         the literal 0x0004.
     *
     * @return bool  true if the $activeFlag bit is set in $flags,
     *                false otherwise. Any other bits present in
     *                $flags (FLAG_ARCHIVED, FLAG_ASSIGN_MEMBERS_ONLY,
     *                etc.) do not affect the result.
     */
    public function isActive($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}

?>
