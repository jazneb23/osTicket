<?php
/*********************************************************************
    TeamEnabledChecker.php

    Extracted MOD-30 is-enabled seam. The facade entry point,
    Team::isEnabled() in include/class.team.php, has no orchestration
    concerns to keep — it is a self-contained inline expression over
    the Team instance's own $flags property. This service lifts that
    exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_ENABLED
    constant value, so Team::FLAG_ENABLED remains the single source of
    truth for the bitmask and is not redefined here.

    Deliberately preserved quirk: the original body returns the raw
    bitwise-AND int result (0 or 1), not a cast bool. Team::getHashtable()
    persists that raw value verbatim into the 'isenabled' hashtable/API
    field, and include/staff/team.inc.php's `!$team->isEnabled()` check
    depends on 0 being the exact disabled sentinel. That must be
    reproduced as-is, not normalized to a real bool.

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into Team::isEnabled() or Team::isActive(), to avoid
    recursion once the facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TeamEnabledChecker {

    /**
     * Core lift of the body of Team::isEnabled().
     *
     * @param int $flags
     *      The Team instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $enabledFlag
     *      The FLAG_ENABLED bitmask value (facade's
     *      self::FLAG_ENABLED), passed in so Team remains the single
     *      source of truth for the constant.
     *
     * @return int
     *      The raw result of the bitwise AND: 0 when the FLAG_ENABLED
     *      bit is clear (disabled), 1 when it is set (enabled). Not
     *      cast to bool.
     */
    public function isEnabled($flags, $enabledFlag) {
        return $flags & $enabledFlag;
    }
}

?>
