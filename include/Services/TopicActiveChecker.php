<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted active check for Topic::isActive() (include/class.topic.php).
    Accepts the raw Topic flags bitmask plus the FLAG_ACTIVE constant and
    reproduces the exact bitwise-AND-then-boolean-cast expression from the
    entry point verbatim. This is an inline-expression lift: no schedule
    resolution, no global $cfg reads, and no other caller-specific
    orchestration belong here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    // Mirrors the core logic of Topic::isActive() exactly, including its
    // double-negation cast (`!!`) to a strict PHP boolean. FLAG_ARCHIVED
    // and FLAG_CUSTOM_NUMBERS are distinct, non-overlapping bits and do
    // not affect this result.
    static function isActive($flags, $flagActive) {
        return !!($flags & $flagActive);
    }
}
