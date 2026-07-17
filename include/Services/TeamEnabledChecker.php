<?php
/*********************************************************************
    TeamEnabledChecker.php

    Lifts the legacy `$this->flags & self::FLAG_ENABLED` expression from
    Team::isEnabled(). Flag loading and facade concerns stay with the
    caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TeamEnabledChecker {

    /**
     * Evaluate whether the enabled flag is set for the bound Team.
     *
     * Lifts the legacy `$this->flags & self::FLAG_ENABLED` expression from
     * Team::isEnabled(). Does not call isEnabled() or isActive() — the
     * strangler stage routes the facade method here. Returns the raw
     * int result of the bitwise AND (0 or 1), not a coerced bool, to
     * preserve caller-visible behavior byte-for-byte.
     *
     * @param Team $team Hydrated Team instance with flags already loaded
     * @return int Raw result of flags & FLAG_ENABLED (0 or 1)
     */
    public function check($team) {
        return $team->flags & Team::FLAG_ENABLED;
    }
}

?>
