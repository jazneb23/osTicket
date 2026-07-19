<?php
/*********************************************************************
    TicketOverdueService.php

    Extracted overdue-state transitions for Ticket: the pure read of the
    persisted isoverdue flag, marking/clearing it (with associated due
    date bookkeeping), and the cron batch scan that finds candidate
    tickets. Alert dispatch, event logging, and ORM persistence remain
    delegated to the passed Ticket instance; schedule/config resolution
    and other facade-level orchestration stay in class.ticket.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

include_once INCLUDE_DIR.'class.orm.php';

class TicketOverdueService {

    /**
     * Pure read of the persisted overdue flag.
     *
     * Lifts the legacy `$this->ht['isoverdue']` expression from
     * Ticket::isOverdue(). Does not call isOverdue() — the strangler stage
     * routes the facade method here.
     *
     * @param Ticket $ticket Hydrated ticket instance
     * @return mixed Raw persisted isoverdue value (truthy/falsy contract)
     */
    public function isOverdue($ticket) {
        return $ticket->ht['isoverdue'];
    }

    /**
     * Mark $ticket overdue, subject to the open-state guard and the
     * already-overdue idempotency no-op.
     *
     * Lifts the legacy body of Ticket::markOverdue() from class.ticket.php.
     * Delegates to $ticket->isOpen(), $ticket->isOverdue(), $ticket->save(),
     * $ticket->logEvent(), and $ticket->onOverdue() — never to markOverdue()
     * itself — so the strangler stage can route the facade method here
     * without recursion.
     *
     * @param Ticket $ticket Ticket instance to transition
     * @param bool $whine Passed through to onOverdue() to gate alert email
     * @return bool False if not open, or if save() fails after setting
     *              isoverdue=1; true if already overdue (no-op) or after a
     *              successful mark+log+alert
     */
    public function markOverdue($ticket, $whine = true) {
        // Only open tickets can be marked overdue
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
     * Clear the overdue flag and any past-due date bookkeeping on $ticket.
     *
     * Lifts the legacy body of Ticket::clearOverdue() from
     * class.ticket.php. Previously logged 'overdue' history events are
     * intentionally left untouched. Delegates to $ticket->isOverdue(),
     * $ticket->getDueDate(), $ticket->getSLADueDate(), and $ticket->save()
     * — never to clearOverdue() itself.
     *
     * @param Ticket $ticket Ticket instance to update
     * @param bool $save When true, persist immediately via save(); when
     *             false, leave the mutated fields dirty on $ticket for a
     *             caller-batched save
     * @return bool save() result when $save is true, else literal true
     */
    public function clearOverdue($ticket, $save = true) {
        //NOTE: Previously logged overdue event is NOT annuled.
        if ($ticket->isOverdue())
            $ticket->isoverdue = 0;

        // clear due date if it's in the past
        if ($ticket->getDueDate() && Misc::db2gmtime($ticket->getDueDate()) <= Misc::gmtime())
            $ticket->duedate = null;

        // Clear SLA if est. due date is in the past
        if ($ticket->getSLADueDate() && Misc::db2gmtime($ticket->getSLADueDate()) <= Misc::gmtime())
            $ticket->est_duedate = null;

        return $save ? $ticket->save() : true;
    }

    /**
     * Batch-scan for open, not-yet-overdue tickets past their due (or est.
     * SLA) date, capped at 100 per invocation, and mark each overdue.
     *
     * Lifts the legacy body of Ticket::checkOverdue() from
     * class.ticket.php. Delegates each match to $ticket->markOverdue()
     * (default $whine=true) rather than reimplementing the mark logic —
     * never to checkOverdue() itself.
     *
     * @param string $ticketClass Fully-qualified Ticket model class to
     *        query against; defaults to Ticket for production use and is
     *        overridable for isolated testing
     * @return void
     */
    public function checkOverdue($ticketClass = 'Ticket') {
        $overdue = $ticketClass::objects()
            ->filter(array(
                'isoverdue' => 0,
                'status__state' => 'open',
                Q::any(array(
                    Q::all(array(
                        'duedate__isnull' => true,
                        'est_duedate__isnull' => false,
                        'est_duedate__lt' => SqlFunction::NOW())
                        ),
                    Q::all(array(
                        'duedate__isnull' => false,
                        'duedate__lt' => SqlFunction::NOW())
                        )
                    ))
                ))
            ->limit(100);

        foreach ($overdue as $ticket)
            $ticket->markOverdue();
    }
}

?>
