<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Extracted MOD-32 is-active seam. The facade entry point,
    Dept::isActive() in include/class.dept.php, has no orchestration
    concerns to keep — it is a self-contained inline expression over
    the Dept instance's own $flags property. This service lifts that
    exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_ACTIVE constant
    value, so Dept::FLAG_ACTIVE remains the single source of truth for
    the bitmask and is not redefined here.

    Note that Dept::getStatus() and DeptListSearch's dept-listing query
    each independently inline their own `flags & FLAG_ACTIVE` checks
    (the former as part of a tri-state active/archived/disabled
    precedence) rather than calling isActive(). Those are separate,
    out-of-scope call sites and are not touched or folded into this
    service.

    Matching the sibling TopicActiveChecker precedent, the original
    body double-negates the bitwise AND (!!) to produce a strict PHP
    boolean, not a raw int. That must be reproduced exactly, since
    some callers are sensitive to true/false vs 1/0/4.

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into Dept::isActive(), to avoid recursion once the
    facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Core lift of the body of Dept::isActive().
     *
     * @param int $flags
     *      The Dept instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $activeFlag
     *      The FLAG_ACTIVE bitmask value (facade's self::FLAG_ACTIVE),
     *      passed in so Dept remains the single source of truth for
     *      the constant.
     *
     * @return bool
     *      Strict PHP boolean: true when the FLAG_ACTIVE bit is set,
     *      false otherwise. Other bits present in $flags (e.g.
     *      FLAG_ARCHIVED or assignment-related flags) do not affect
     *      the result.
     */
    public function isActive($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}

?>
