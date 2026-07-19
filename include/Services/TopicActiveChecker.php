<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted core logic for Topic::isActive(). The entry-point
    method body IS the core logic — a self-contained inline expression
    with no sub-method delegation — so it is lifted verbatim here,
    operator-for-operator.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    // Mirrors `return !!($this->flags & self::FLAG_ACTIVE);` from
    // Topic::isActive() exactly. $flags is the Topic instance's own
    // flags bitmask (Topic::$flags); $activeFlag is Topic::FLAG_ACTIVE,
    // passed in by the caller so this service does not duplicate the
    // magic 0x0002 constant. The bitwise AND result is double-negated
    // to coerce it to a strict bool, matching the original exactly.
    function check($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }
}

?>
