<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 seam. include/class.topic.php's Topic::isActive()
    is a self-contained inline expression with no sub-delegation, so
    this service lifts that expression verbatim (pattern B: inline
    expression lift) rather than delegating to another class/method.

    `!!($flags & Topic::FLAG_ACTIVE)` uses the bitwise AND (&) to
    isolate bit 0x0002 of the flags bitmask, then double-negates (!!)
    to coerce the int result to a strict boolean. Other bits (e.g.
    FLAG_ARCHIVED = 0x0004, FLAG_CUSTOM_NUMBERS = 0x0001) must not
    affect the result, and the return value must remain true/false,
    not a truthy/falsy int.

    This service reads the flags int directly (passed by the caller,
    e.g. $topic->flags) and never calls back into Topic::isActive() or
    Topic::isEnabled() (the entry-point methods it replaces), to avoid
    recursion once the facade delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Exact lift of Topic::isActive()'s body. Preserves the bitwise
     * AND (&) against FLAG_ACTIVE (0x0002) and the double-negation
     * (!!) verbatim — returns a strict boolean, true iff FLAG_ACTIVE
     * is set, regardless of any other flags also set.
     *
     * @param int $flags the raw help-topic flags bitmask (e.g. from
     *   $topic->flags)
     * @return bool
     */
    public function isActive($flags) {
        return !!($flags & Topic::FLAG_ACTIVE);
    }
}

?>
