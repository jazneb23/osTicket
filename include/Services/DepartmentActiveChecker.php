<?php
/**
 * DepartmentActiveChecker
 *
 * Extracted core logic for Dept::isActive() (include/class.dept.php).
 * Pure function of a department's `flags` bitmask — no DB access, no
 * Dept::lookup() calls, and no global state. Callers must hydrate `flags`
 * (e.g. via the ORM or Dept::save()) before invoking this checker.
 */
class DepartmentActiveChecker {

    /**
     * Determine whether the FLAG_ACTIVE bit is set on a department's flags
     * bitmask. Examines only the FLAG_ACTIVE bit; all other bits (e.g.
     * FLAG_ARCHIVED) have no effect on the result.
     *
     * @param int $flags       The department's raw `flags` bitmask.
     * @param int $activeFlag  The FLAG_ACTIVE bit value (Dept::FLAG_ACTIVE).
     *
     * @return bool Strict boolean — true if FLAG_ACTIVE is set, false otherwise.
     */
    static function isActive($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}
