<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 is-active seam. The facade entry point,
    Topic::isActive() in include/class.topic.php, has no orchestration
    concerns to keep — it is a self-contained inline expression over
    the Topic instance's own $flags property. This service lifts that
    exact expression verbatim.

    The facade hands this service the already-hydrated flags bitmask
    (never touching the database itself) plus the FLAG_ACTIVE constant
    value, so Topic::FLAG_ACTIVE remains the single source of truth
    for the bitmask and is not redefined here.

    Unlike the MOD-30 Team::isEnabled() precedent, the original body
    here double-negates the bitwise AND (!!) to produce a strict PHP
    boolean, not a raw int. That must be reproduced exactly, since
    some callers are sensitive to true/false vs 1/0.

    This service performs no delegation to other methods or classes
    (there is none to delegate to in the original body), and never
    calls back into Topic::isActive() or Topic::isEnabled(), to avoid
    recursion once the facade delegates here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Core lift of the body of Topic::isActive().
     *
     * @param int $flags
     *      The Topic instance's already-hydrated flags bitmask
     *      (facade's $this->flags). Not loaded or looked up here.
     * @param int $activeFlag
     *      The FLAG_ACTIVE bitmask value (facade's self::FLAG_ACTIVE),
     *      passed in so Topic remains the single source of truth for
     *      the constant.
     *
     * @return bool
     *      Strict PHP boolean: true when the FLAG_ACTIVE bit is set,
     *      false otherwise. Other bits present in $flags (e.g.
     *      FLAG_ARCHIVED, FLAG_CUSTOM_NUMBERS) do not affect the
     *      result.
     */
    public function isActive($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}

?>
