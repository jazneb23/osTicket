<?php
/*********************************************************************
    FileSizeFormatter.php

    Extracted MOD-33 file-size-formatting seam. The facade method
    Format::file_size($bytes) in include/class.format.php (lines
    23-33) delegates here for its entire body — the method is a
    single flat static function with no sub-delegation (no helper
    calls, no object hydration, no recursion), so this is an "inline
    expression lift" (pattern B), not a sub-method delegation.

    Facade concerns that stay in include/class.format.php (per the
    seam manifest, this class must NOT re-implement or absorb them):
      - The static Format::file_size($bytes) facade signature itself
        and its callers across include/class.thread.php,
        include/class.forms.php, include/staff/settings-system.inc.php,
        and the various *.tmpl.php templates listed in the seam
        manifest — all out of scope here.
      - Any future schedule/config resolution the strangler stage may
        layer onto the facade — none exists today, and none is added
        here.

    CRITICAL constraints preserved verbatim from the original method
    body (do not "fix" or "simplify" any of these):
      - Non-numeric $bytes returns the original $bytes value
        unchanged (exact passthrough, not cast to string), guarded by
        !is_numeric($bytes) as the very first check.
      - The bytes/kb boundary is a strict less-than at 1024:
        $bytes == 1023 formats as 'bytes', $bytes == 1024 formats as
        'kb'.
      - The kb/mb boundary is a strict less-than at (900<<10) ==
        921600 — an osTicket-specific non-round threshold, NOT the
        naive 1MB (1048576) cutoff. 921599 -> kb, 921600 -> mb.
      - Rounding uses PHP's round($x, 1) (one decimal place, default
        half-away-from-zero mode) for both the kb and mb branches.
      - Output suffixes are exactly ' bytes', ' kb', ' mb' (leading
        space, lowercase, no pluralization changes) since callers
        interpolate the return value directly into visible UI text.

    This class never calls back into Format::file_size(); it operates
    purely on the $bytes value passed in by the facade, so there is no
    recursion risk once the facade delegates to it. It is a pure
    function with no side effects (no DB calls, no reads/writes of
    $cfg or other globals, no lazy-loading, no caching, no file I/O),
    safe to call in tight loops (e.g. the doubling loops in
    include/class.forms.php and include/staff/settings-system.inc.php)
    or during page rendering.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class FileSizeFormatter {

    /**
     * Exact lift of the body of Format::file_size().
     *
     * @param mixed $bytes
     *   A scalar byte count — expected to be an int or a numeric
     *   string (e.g. an attachment's stored size field, or a
     *   power-of-two loop variable). No type coercion or validation
     *   beyond is_numeric() is performed before use.
     *
     * @return mixed
     *   A formatted string ('{$bytes} bytes', '{rounded} kb', or
     *   '{rounded} mb') for numeric input, or the original $bytes
     *   value completely unchanged (original type/value) when
     *   is_numeric($bytes) is false.
     */
    public function format($bytes) {

        if(!is_numeric($bytes))
            return $bytes;
        if($bytes<1024)
            return $bytes.' bytes';
        if($bytes < (900<<10))
            return round(($bytes/1024),1).' kb';

        return round(($bytes/1048576),1).' mb';
    }
}

?>
