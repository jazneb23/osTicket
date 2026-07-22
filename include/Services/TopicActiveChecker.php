<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 seam. The facade method Topic::isActive() in
    include/class.topic.php delegates here. Topic::isEnabled() is a
    pure pass-through delegate of isActive() and continues to call
    isActive() on the facade — it is not touched by this extraction.

    This is an inline-expression lift: the entry-point method body is a
    single self-contained expression with no sub-delegation, so the
    exact expression (including its bitwise & operator and the !!
    double-negation cast) is lifted verbatim rather than reimplemented.
    This service reads the passed Topic instance's already-hydrated
    `flags` property directly — it never calls back into
    Topic::isActive() or Topic::isEnabled(), to avoid recursion.

    Preserves the bitwise & operator against Topic::FLAG_ACTIVE
    (0x0002), double-negated to force a strict PHP bool, and ignores
    the unrelated FLAG_ARCHIVED (0x0004) and FLAG_CUSTOM_NUMBERS
    (0x0001) bits entirely, consistent with the original inline check.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Exact lift of Topic::isActive(). Reads $topic->flags directly
     * and preserves the bitwise & operator against
     * Topic::FLAG_ACTIVE (0x0002) verbatim, double-negated to return
     * a strict PHP bool rather than a raw truthy/falsy int.
     */
    public function isActive(Topic $topic) {
        return !!($topic->flags & Topic::FLAG_ACTIVE);
    }
}

?>
