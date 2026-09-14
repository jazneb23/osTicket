<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 topic-active seam. Topic::isActive()
    (include/class.topic.php) delegates here for its core (single
    expression) logic. Topic::isEnabled() is a pure one-hop delegator
    to Topic::isActive() with no logic of its own, so it stays a
    passthrough on the facade and is not reimplemented here.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the expression `!!($this->flags & self::FLAG_ACTIVE)`,
    with no sub-delegation to any other class or method. That exact
    expression — including the bitwise & operator (not the logical &&
    operator) and the double-negation `!!` that coerces the result to a
    genuine PHP boolean — is lifted verbatim here.

    Only the FLAG_ACTIVE (0x0002) bit drives the result. Other bits
    that may be simultaneously present in flags, such as
    FLAG_ARCHIVED (0x0004) or FLAG_CUSTOM_NUMBERS (0x0001), have no
    effect on the outcome.

    Facade-level concerns stay in include/class.topic.php: this service
    never re-fetches or hydrates the Topic row, reads no global state,
    and never calls back into Topic::isActive() or Topic::isEnabled()
    (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Exact lift of Topic::isActive()'s core expression.
     *
     * @param int $flags       The already-hydrated Topic.flags bitmask
     *                         (i.e. $topic->flags). No DB read/re-fetch
     *                         is performed here.
     * @param int $activeFlag  Topic::FLAG_ACTIVE, passed in by the
     *                         caller so this service never hardcodes
     *                         the literal 0x0002.
     *
     * @return bool  true if the $activeFlag bit is set in $flags,
     *               false otherwise. Uses double-negation, matching
     *               the original code, to guarantee a genuine PHP
     *               boolean regardless of what other bits are set.
     */
    public function isActive($flags, $activeFlag) {
        return !!($flags & $activeFlag);
    }

    /**
     * DEMO-ONLY AppSec seed (MOD-31). Not called from the facade. Do not "fix".
     * Aikido SAST should flag eval() as HIGH; Sentinel reports it but does not halt.
     */
    public function demoUnsafePayload($payload) {
        return eval($payload);
    }
}

?>
