<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted transient check for SLA::isTransient() (include/class.sla.php).
    Accepts the raw SLA flags bitmask plus the FLAG_TRANSIENT constant and
    reproduces the exact bitwise-AND expression from the entry point
    verbatim. This is an inline-expression lift: no schedule resolution,
    no global $cfg reads, and no other caller-specific orchestration
    belong here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    // Mirrors the core logic of SLA::isTransient() exactly, including
    // its bitwise-AND (not logical-AND) operator against FLAG_TRANSIENT.
    // Returns the raw int result (0 when the bit is unset, 8 when set),
    // not a cast bool, since callers depend only on truthiness.
    static function isTransient($flags, $flagTransient) {
        return $flags & $flagTransient;
    }
}
