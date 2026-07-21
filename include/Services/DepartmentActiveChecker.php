<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 seam. include/class.dept.php's Dept::isActive()
    is a self-contained inline expression with no sub-delegation, so
    this service lifts that expression verbatim (pattern B: inline
    expression lift) rather than delegating to another class/method.

    `!!($this->flags & self::FLAG_ACTIVE)` uses the bitwise AND (&)
    against Dept::FLAG_ACTIVE (0x0004) and coerces the result to a
    strict boolean via double-negation. Only that single bit matters:
    FLAG_ARCHIVED and every other flag bit have zero effect here, and
    a null/unset flags value naturally coerces to 0 via PHP's bitwise
    AND, yielding false — no different default is introduced.

    This service accepts the raw flags integer (not a Dept instance)
    so the facade method becomes a thin one-line delegation, and it
    never calls back into Dept::isActive() (the entry-point method it
    replaces), to avoid recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of Dept::isActive()'s body. Preserves the bitwise
     * AND (&) against FLAG_ACTIVE (0x0004) and the double-negation
     * (!!) verbatim, returning a strict boolean.
     *
     * @param int $flags raw department flags bitmask (e.g. $dept->flags)
     * @return bool true iff the FLAG_ACTIVE bit (0x0004) is set
     */
    public function isActive($flags) {
        return !!($flags & Dept::FLAG_ACTIVE);
    }
}

?>
