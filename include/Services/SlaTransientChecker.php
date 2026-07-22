<?php
/*********************************************************************
    SlaTransientChecker.php

    Extracted MOD-29 transient-flag seam. include/class.sla.php's
    SLA::isTransient() delegates here once the strangler stage patches
    the facade.

    coreLogic for this seam IS the entry-point method body itself: a
    bare inline expression `$this->flags & self::FLAG_TRANSIENT` with
    no deeper delegation chain. Per the manifest, this is a pure
    "Lifted logic" pattern (pattern B), so this service simply lifts
    that exact expression verbatim, including the bitwise-AND
    operator and its raw int return type.

    Unlike a boolean-coerced check, isTransient() returns the literal
    result of the `&` operation (0 or 8, not true/false). Do NOT
    "upgrade" this to `!= 0`, `=== `, or a `(bool)` cast -- reproduce
    the exact int-typed truthy/falsy value the legacy method returns.

    This service performs a plain in-memory property read with no
    database access, no global state reads, no lazy-loading of
    related objects, no caching, no file I/O, and no
    translation/localization calls. It never calls back into
    SLA::isTransient(), to avoid recursion once the facade delegates
    to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class SlaTransientChecker {

    /**
     * Exact lift of the core body of SLA::isTransient():
     * `$this->flags & self::FLAG_TRANSIENT`.
     *
     * Preserves the legacy bitwise-AND (`&`) operator verbatim -- this
     * is a faithful, bug-for-bug (i.e. bug-free) port of the legacy
     * facade method, not a reimplementation with different operators,
     * guards, or type coercions.
     *
     * @param SLA $sla the owning SLA instance (read-only access to its
     *        already-hydrated flags property)
     * @return int the raw result of `$sla->flags & SLA::FLAG_TRANSIENT`
     *        -- 0 when the FLAG_TRANSIENT bit is unset, or
     *        SLA::FLAG_TRANSIENT (8) when it is set. Not coerced to
     *        bool; callers use it only in boolean/truthiness context.
     */
    public function isTransient(SLA $sla) {
        return $sla->flags & SLA::FLAG_TRANSIENT;
    }
}

?>
