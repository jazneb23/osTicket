<?php
/*********************************************************************
    TeamEnabledChecker.php

    Extracted core logic for Team::isEnabled(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TeamEnabledChecker {

    // Mirrors `return $this->flags & self::FLAG_ENABLED;` from
    // Team::isEnabled() exactly. $flags is the Team instance's own flags
    // bitmask (Team::$flags); $enabledFlag is Team::FLAG_ENABLED, passed
    // in by the caller so this service does not duplicate the magic
    // 0x0001 constant. The raw int result (0 or 1) is returned as-is
    // (not cast to bool), since getHashtable() stores it directly and
    // other callers rely on truthy if/ternary semantics.
    function check($flags, $enabledFlag) {
        return $flags & $enabledFlag;
    }
}

?>
