<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted core logic for Dept::isActive(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    // Mirrors `return !!($this->flags & self::FLAG_ACTIVE);` from
    // Dept::isActive() exactly. $flags is the Dept instance's own
    // flags bitmask (Dept::$flags); $activeFlag is Dept::FLAG_ACTIVE,
    // passed in by the caller so this service does not duplicate the
    // magic 0x0004 constant. The bitwise AND result is double-negated
    // to coerce it to a strict bool, matching the original exactly.
    function check($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}

?>
