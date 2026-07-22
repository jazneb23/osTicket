<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 active-flag seam. include/class.dept.php's
    Dept::isActive() delegates here once the strangler stage patches
    the facade.

    coreLogic for this seam IS the entry-point method body itself: a
    self-contained inline expression `!!($this->flags & self::FLAG_ACTIVE)`
    with no deeper delegation chain. Per the manifest, this is pattern B
    ("Inline expression lift"), so this service lifts that exact
    expression verbatim, including the bitwise-AND operator and the
    double-negation that coerces the result to a real PHP bool.

    Dept::isActive() does NOT call Dept::hasFlag()
    (`($this->get('flags', 0) & $flag) != 0`) -- it performs its own
    inline bitwise check. This service reproduces that literal
    expression, not hasFlag()'s formula, even though the two are
    numerically equivalent for a single bit.

    Only the FLAG_ACTIVE (0x0004) bit is inspected. FLAG_ASSIGN_MEMBERS_ONLY
    (0x0001), FLAG_DISABLE_AUTO_CLAIM (0x0002), FLAG_ARCHIVED (0x0008),
    FLAG_ASSIGN_PRIMARY_ONLY (0x0010), and FLAG_DISABLE_REOPEN_AUTO_ASSIGN
    (0x0020) have zero effect on the result, even when combined with
    FLAG_ACTIVE -- e.g. a department with FLAG_ARCHIVED set but
    FLAG_ACTIVE clear still returns false.

    This service performs a plain in-memory property read with no
    database access, no global state reads ($cfg/$ost), no caching,
    and no file I/O. It never calls back into Dept::isActive(), to
    avoid recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Exact lift of the core body of Dept::isActive():
     * `!!($this->flags & self::FLAG_ACTIVE)`.
     *
     * Preserves the legacy bitwise-AND (`&`) check against only the
     * FLAG_ACTIVE bit, and the double-negation (`!!`) that coerces the
     * result to a real PHP bool -- this is a faithful, bug-for-bug
     * (i.e. bug-free) port of the legacy facade method, not a
     * reimplementation with different operators, guards, or fallbacks.
     * Deliberately does not use Dept::hasFlag() or Dept::get(), whose
     * formula differs from this inline expression.
     *
     * @param Dept $dept the owning Dept instance (read-only access to
     *        its already-hydrated flags property)
     * @return bool true when the FLAG_ACTIVE (0x0004) bit is set in
     *        $dept->flags, false otherwise -- regardless of the state
     *        of any other flag bits (FLAG_ASSIGN_MEMBERS_ONLY,
     *        FLAG_DISABLE_AUTO_CLAIM, FLAG_ARCHIVED,
     *        FLAG_ASSIGN_PRIMARY_ONLY, FLAG_DISABLE_REOPEN_AUTO_ASSIGN).
     */
    public function isActive(Dept $dept) {
        return !!($dept->flags & Dept::FLAG_ACTIVE);
    }
}

?>
