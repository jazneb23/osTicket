<?php
/*********************************************************************
    TeamEnabledChecker.php

    Extracted enabled check for Team::isEnabled() (include/class.team.php).
    Accepts the raw Team flags bitmask plus the FLAG_ENABLED constant and
    reproduces the exact bitwise-AND expression from the entry point
    verbatim. This is an inline-expression lift: no schedule resolution,
    no global $cfg reads, and no other caller-specific orchestration
    belong here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TeamEnabledChecker {

    // Mirrors the core logic of Team::isEnabled() exactly, including
    // its bitwise-AND (not logical-AND) operator against FLAG_ENABLED.
    // Returns the raw int result (0 when the bit is unset, 1 when set),
    // not a cast bool, since callers like getHashtable() store this
    // raw value. Evaluated independently of FLAG_NOALERTS.
    static function isEnabled($flags, $flagEnabled) {
        return $flags & $flagEnabled;
    }
}
