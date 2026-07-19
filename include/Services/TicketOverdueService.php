<?php
/*********************************************************************
    TicketOverdueService.php

    Extracted MOD-27 overdue/escalation seam. Facade methods in
    include/class.ticket.php delegate here for:
      - isOverdue()
      - markOverdue($whine)
      - clearOverdue($save)
      - checkOverdue() (cron-driven batch scan)

    Each method receives the owning Ticket instance and calls back into
    it for facade-level behavior (isOpen(), save(), logEvent(), onOverdue(),
    getDueDate(), getSLADueDate()) — this service never calls back into the
    entry-point methods it replaces, to avoid recursion once the facade
    delegates to it.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class TicketOverdueService {

    /**
     * Pure lift of Ticket::isOverdue() — reads the persisted flag, no
     * recomputation and no side effects.
     */
    public function isOverdue(Ticket $ticket) {
        return $ticket->ht['isoverdue'];
    }

    /**
     * Exact lift of Ticket::markOverdue($whine). Only open tickets can be
     * marked overdue; already-overdue tickets are a no-op; save() must
     * succeed before the overdue event is logged and onOverdue() fires.
     */
    public function markOverdue(Ticket $ticket, $whine = true) {

        if (!$ticket->isOpen())
            return false;

        if ($ticket->isOverdue())
            return true;

        $ticket->isoverdue = 1;
        if (!$ticket->save())
            return false;

        $ticket->logEvent('overdue');
        $ticket->onOverdue($whine);

        return true;
    }

    /**
     * Exact lift of Ticket::clearOverdue($save). Never annuls a previously
     * logged overdue event; only nulls duedate/est_duedate when they are
     * set AND in the past; honors $save (persist vs. in-memory only).
     */
    public function clearOverdue(Ticket $ticket, $save = true) {

        // NOTE: Previously logged overdue event is NOT annulled.
        if ($ticket->isOverdue())
            $ticket->isoverdue = 0;

        if ($ticket->getDueDate() && Misc::db2gmtime($ticket->getDueDate()) <= Misc::gmtime())
            $ticket->duedate = null;

        if ($ticket->getSLADueDate() && Misc::db2gmtime($ticket->getSLADueDate()) <= Misc::gmtime())
            $ticket->est_duedate = null;

        return $save ? $ticket->save() : true;
    }

    /**
     * Batch scan for Ticket::checkOverdue().
     *
     * *** KNOWN DEMO REGRESSION (MOD-27) — DO NOT "FIX" VIA A FRESH EXTRACTOR
     * RUN WITHOUT UPDATING THE PIPELINE STATE ***
     *
     * This intentionally does NOT match the manifest's precedence
     * constraint for orchestrator/.state/MOD-27-manifest.json:
     *
     *   "whenever duedate IS SET (not null), est_duedate is ignored
     *    entirely for batch selection purposes, even if est_duedate is
     *    also in the past and duedate is not."
     *
     * The two Q::all() branches below are checked independently (a plain
     * OR), instead of gating the est_duedate branch on `duedate__isnull`.
     * That means a ticket with a FUTURE duedate but a PAST est_duedate
     * gets incorrectly selected and marked overdue — the original inline
     * body in include/class.ticket.php does not have this bug (see the
     * Q::any/Q::all nesting there for the correct, null-gated version).
     *
     * This is a pinned parity-gate regression fixture for the demo
     * (see orchestrator/fixtures/MOD-27/checkoverdue-duedate-precedence-batch.json
     * and orchestrator/lib/manifest.ts PINNED_MANIFESTS) — fixing it here
     * is exactly the correct real-world remediation once the demo has run.
     */
    public function checkOverdue($ticketClass = 'Ticket') {
        $overdue = $ticketClass::objects()
            ->filter(array(
                'isoverdue' => 0,
                'status__state' => 'open',
                Q::any(array(
                    Q::all(array(
                        'duedate__isnull' => false,
                        'duedate__lt' => SqlFunction::NOW())
                        ),
                    Q::all(array(
                        'est_duedate__isnull' => false,
                        'est_duedate__lt' => SqlFunction::NOW())
                        )
                    ))
                ))
            ->limit(100);

        foreach ($overdue as $ticket)
            $ticket->markOverdue();
    }
}

?>
