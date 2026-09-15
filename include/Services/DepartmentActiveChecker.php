<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 department-active seam. Dept::isActive() in
    include/class.dept.php is a self-contained inline expression with
    no sub-delegation (`!!($this->flags & self::FLAG_ACTIVE)`), so per
    the "inline expression lift" pattern this service lifts that exact
    expression rather than delegating to any sub-method.

    This service accepts the raw `flags` int directly (not the Dept
    object) and never calls back into Dept::isActive() or any other
    Dept instance method, to avoid recursion once the facade delegates
    to it. The independent, out-of-chain flag reads in
    Dept::getStatus(), Dept::allowsReopen(), Dept::hasFlag(), the
    `flags__hasbit` ORM filter, and the getDepartments() bulk-list loop
    stay on Dept and are out of scope for this extraction. All facade
    concerns beyond reading $this->flags (which the caller passes in)
    remain in include/class.dept.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of the core logic in Dept::isActive():
     * `!!($flags & Dept::FLAG_ACTIVE)`.
     *
     * This is a bitwise AND against Dept::FLAG_ACTIVE (0x0004) only,
     * double-negated to a strict PHP boolean. None of the other packed
     * bits on the Dept `flags` column — FLAG_ASSIGN_MEMBERS_ONLY
     * (0x0001), FLAG_DISABLE_AUTO_CLAIM (0x0002), FLAG_ARCHIVED
     * (0x0008), FLAG_ASSIGN_PRIMARY_ONLY (0x0010), or
     * FLAG_DISABLE_REOPEN_AUTO_ASSIGN (0x0020) — are read here or have
     * any effect on the result, even when set simultaneously with
     * FLAG_ACTIVE.
     *
     * @param int $flags
     *   The raw, already-loaded Dept::flags bitfield value (the
     *   caller must pass $this->flags from the Dept facade; this
     *   service performs no DB read, lazy-load, or global $cfg read
     *   of its own).
     *
     * @return bool
     *   true when the FLAG_ACTIVE bit is set, false otherwise
     *   (including when flags is 0).
     */
    public function isActive($flags) {
        return !!($flags & Dept::FLAG_ACTIVE);
    }
}

?>
