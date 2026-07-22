<?php
/*********************************************************************
    TeamEnabledChecker.php

    Extracted MOD-30 enabled-flag seam. include/class.team.php's
    Team::isEnabled() delegates here once the strangler stage patches
    the facade.

    coreLogic for this seam IS the entry-point method body itself: a
    bare inline expression `$this->flags & self::FLAG_ENABLED` with no
    deeper delegation chain. Per the manifest, this is a pure "Lifted
    logic" pattern (pattern B), so this service simply lifts that
    exact expression verbatim, including the bitwise-AND operator and
    its raw int return type.

    Unlike a boolean-coerced check, isEnabled() returns the literal
    result of the `&` operation (0 or 1, not true/false) -- this
    matters because Team::getHashtable() persists that raw int
    directly into $base['isenabled']. Do NOT "upgrade" this to
    `!= 0`, `=== `, or a `(bool)` cast -- reproduce the exact int-typed
    truthy/falsy value the legacy method returns.

    FLAG_NOALERTS (0x0002) has zero effect here: only the FLAG_ENABLED
    (0x0001) bit is inspected, regardless of any other bits present in
    flags.

    This service performs a plain in-memory property read with no
    database access, no global state reads, no lazy-loading of
    related objects, no caching, no file I/O, and no
    translation/localization calls. It never calls back into
    Team::isEnabled() or Team::isActive(), to avoid recursion once the
    facade delegates to it. Team::isActive() itself is left untouched
    and continues to delegate to isEnabled()'s new implementation
    transitively.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TeamEnabledChecker {

    /**
     * Exact lift of the core body of Team::isEnabled():
     * `$this->flags & self::FLAG_ENABLED`.
     *
     * Preserves the legacy bitwise-AND (`&`) operator verbatim -- this
     * is a faithful, bug-for-bug (i.e. bug-free) port of the legacy
     * facade method, not a reimplementation with different operators,
     * guards, or type coercions.
     *
     * @param Team $team the owning Team instance (read-only access to
     *        its already-hydrated flags property)
     * @return int the raw result of `$team->flags & Team::FLAG_ENABLED`
     *        -- 0 when the FLAG_ENABLED bit is unset, or
     *        Team::FLAG_ENABLED (1) when it is set. Not coerced to
     *        bool; most callers use it only in boolean/truthiness
     *        context, but Team::getHashtable() persists this exact
     *        int value into $base['isenabled'].
     */
    public function isEnabled(Team $team) {
        return $team->flags & Team::FLAG_ENABLED;
    }
}

?>
