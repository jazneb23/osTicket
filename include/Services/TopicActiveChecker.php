<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 active-flag seam. include/class.topic.php's
    Topic::isActive() delegates here once the strangler stage patches
    the facade. Topic::isEnabled() is a pure one-line delegate to
    isActive() and continues to forward through the patched facade
    method, so it requires no independent extraction.

    coreLogic for this seam IS the entry-point method body itself: a
    self-contained inline expression `!!($this->flags & self::FLAG_ACTIVE)`
    with no deeper delegation chain. Per the manifest, this is pattern B
    ("Inline expression lift"), so this service lifts that exact
    expression verbatim, including the bitwise-AND operator and the
    double-negation that coerces the result to a real PHP bool.

    Topic::isActive() does NOT call Topic::hasFlag() (which has a
    different operator-precedence bug: `$this->flags & $flag != 0`) --
    it performs its own inline bitwise check. This service reproduces
    that literal expression, not hasFlag()'s semantics.

    Only the FLAG_ACTIVE (0x0002) bit is inspected. FLAG_ARCHIVED
    (0x0004) and FLAG_CUSTOM_NUMBERS (0x0001) have zero effect on the
    result, even when combined with FLAG_ACTIVE -- e.g. a topic with
    FLAG_ARCHIVED set but FLAG_ACTIVE clear still returns false.

    This service performs a plain in-memory property read with no
    database access, no global state reads ($cfg/$ost), no caching,
    and no file I/O. It never calls back into Topic::isActive() or
    Topic::isEnabled(), to avoid recursion once the facade delegates
    to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Exact lift of the core body of Topic::isActive():
     * `!!($this->flags & self::FLAG_ACTIVE)`.
     *
     * Preserves the legacy bitwise-AND (`&`) check against only the
     * FLAG_ACTIVE bit, and the double-negation (`!!`) that coerces the
     * result to a real PHP bool -- this is a faithful, bug-for-bug
     * (i.e. bug-free) port of the legacy facade method, not a
     * reimplementation with different operators, guards, or fallbacks.
     * Deliberately does not use Topic::hasFlag(), whose precedence
     * differs from this inline expression.
     *
     * @param Topic $topic the owning Topic instance (read-only access
     *        to its already-hydrated flags property)
     * @return bool true when the FLAG_ACTIVE (0x0002) bit is set in
     *        $topic->flags, false otherwise -- regardless of the
     *        state of any other flag bits (FLAG_ARCHIVED,
     *        FLAG_CUSTOM_NUMBERS).
     */
    public function isActive(Topic $topic) {
        return !!($topic->flags & Topic::FLAG_ACTIVE);
    }
}

?>
