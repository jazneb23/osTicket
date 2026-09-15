<?php
/*********************************************************************
    TopicActiveChecker.php

    Extracted MOD-31 topic-active seam. Topic::isActive() in
    include/class.topic.php is a self-contained inline expression with
    no sub-delegation (Topic::isEnabled() is pure delegation to
    isActive() with no additional logic), so per the "inline
    expression lift" pattern this service lifts that exact expression
    rather than delegating to any sub-method.

    This service accepts the raw `flags` int directly (not the Topic
    object) and never calls back into Topic::isActive() or
    Topic::isEnabled() (the entry-point methods it replaces), or into
    any other Topic instance method, to avoid recursion once the
    facade delegates to it. The independent, out-of-chain flag reads
    in Topic::getStatus() and the static Topic::getHelpTopics() are
    not part of this extraction. All facade concerns beyond reading
    $this->flags (which the caller passes in) remain in
    include/class.topic.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TopicActiveChecker {

    /**
     * Exact lift of the core logic in Topic::isActive():
     * `!!($flags & Topic::FLAG_ACTIVE)`.
     *
     * This is a bitwise AND against Topic::FLAG_ACTIVE (0x0002) only,
     * double-negated (`!!`) to coerce the result to a strict PHP
     * boolean rather than a truthy/falsy int. Topic::FLAG_CUSTOM_NUMBERS
     * (0x0001) and Topic::FLAG_ARCHIVED (0x0004) are never read here
     * and have zero effect on the result.
     *
     * @param int $flags
     *   The raw, already-loaded Topic::flags bitfield value (the
     *   caller must pass $this->flags from the Topic facade; this
     *   service performs no DB read, lazy-load, or global $cfg read
     *   of its own).
     *
     * @return bool
     *   true if the FLAG_ACTIVE bit is set in $flags, false otherwise
     *   (including when $flags is 0).
     */
    public function isActive($flags) {
        return !!($flags & Topic::FLAG_ACTIVE);
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
