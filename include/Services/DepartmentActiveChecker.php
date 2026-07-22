<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 seam. The facade method Dept::isActive() in
    include/class.dept.php delegates here.

    This is an inline-expression lift: the entry-point method body is a
    single self-contained expression with no sub-delegation, so the
    exact expression (including its bitwise & operator and the
    double-negation) is lifted verbatim rather than reimplemented. This
    service reads the passed Dept instance's already-hydrated `flags`
    property directly — it never calls back into Dept::isActive(), to
    avoid recursion.

    Preserves the double-negation (!!) so a true PHP bool is returned
    rather than the raw int PHP's & operator would otherwise yield, and
    checks only the FLAG_ACTIVE bit (0x0004) against Dept::flags,
    ignoring FLAG_ARCHIVED (0x0008) and all other flag bits entirely,
    consistent with the original inline check.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of Dept::isActive(). Reads $dept->flags directly and
     * preserves the bitwise & operator against Dept::FLAG_ACTIVE
     * (0x0004) and the double-negation (!!) verbatim, returning a
     * coerced strict bool rather than the raw int result.
     */
    public function isActive(Dept $dept) {
        return !!($dept->flags & Dept::FLAG_ACTIVE);
    }
}

?>
