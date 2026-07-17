<?php
/*********************************************************************
    DepartmentActiveChecker.php

    Lifts the legacy `!!($this->flags & self::FLAG_ACTIVE)` expression from
    Dept::isActive(). Flag loading and facade concerns stay with the
    caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class DepartmentActiveChecker {

    /**
     * Evaluate whether the active flag is set for the bound Dept.
     *
     * Lifts the legacy `!!($this->flags & self::FLAG_ACTIVE)` expression from
     * Dept::isActive(). Does not call isActive() — the strangler stage
     * routes the facade method here.
     *
     * @param Dept $dept Hydrated Dept instance with flags already loaded
     * @return bool True if FLAG_ACTIVE is set in flags, false otherwise
     */
    public function check($dept) {
        return !!($dept->flags & Dept::FLAG_ACTIVE);
    }
}

?>
