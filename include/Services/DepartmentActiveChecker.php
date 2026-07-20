<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 is-active seam. Dept::isActive() in
    include/class.dept.php delegates here for its entire body, which
    is a single inline expression with no sub-delegation.

    This is an Inline Expression Lift: the service reproduces the
    exact `!!($this->flags & Dept::FLAG_ACTIVE)` expression verbatim
    (strict boolean double-negation of the bitwise-AND result),
    operating on a passed-in flags value rather than calling back
    into Dept::isActive(), to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of the core expression from Dept::isActive().
     * Takes the already-hydrated flags bitmask (Dept::$flags) and
     * returns a strict boolean: true when the FLAG_ACTIVE (0x0004)
     * bit is set, false otherwise. The FLAG_ARCHIVED (0x0008) bit
     * is intentionally not consulted here, matching the original
     * method's independence from archived status.
     */
    public function isActive($flags) {
        return !!($flags & Dept::FLAG_ACTIVE);
    }
}

?>
