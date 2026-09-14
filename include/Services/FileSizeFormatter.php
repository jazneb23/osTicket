<?php
/*********************************************************************
    FileSizeFormatter.php

    Extracted MOD-33 file-size-formatting seam. Format::file_size()
    (include/class.format.php) delegates here for its core logic.

    This is an inline-expression lift (pattern B): the entry point's
    entire body IS the core logic — a non-numeric guard followed by
    three branches (bytes / kb / mb) with no delegation to any other
    class or method. That exact logic, including the literal
    `(900<<10)` kb/mb threshold and the strict `<` comparisons, is
    lifted verbatim here.

    Facade-level concerns stay in include/class.format.php: this
    service reads no global state ($cfg or otherwise), performs no
    I/O, and never calls back into Format::file_size() (anti-recursion).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class FileSizeFormatter {

    /**
     * Exact lift of Format::file_size()'s core logic.
     *
     * @param mixed $bytes  Int, float, or numeric string byte count;
     *                      may also be a non-numeric value, which is
     *                      passed through unchanged.
     *
     * @return mixed  $bytes unchanged if non-numeric; otherwise a
     *                formatted string: '<bytes> bytes', '<kb> kb', or
     *                '<mb> mb'.
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
