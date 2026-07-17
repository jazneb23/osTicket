<?php
/*********************************************************************
    TicketOverdueTransitions.php

    Extracted overdue-transition logic for Ticket::isOverdue(),
    Ticket::markOverdue($whine), Ticket::clearOverdue($save), and the
    static Ticket::checkOverdue() batch sweep (include/class.ticket.php).
    Each method accepts the Ticket instance (or, for checkOverdue(),
    operates over the Ticket ORM class itself) and reproduces the exact
    guards, assignments, and delegated calls from the entry points
    verbatim.

    Facade-owned orchestration that the manifest attributes to the
    entry points themselves -- $this->save(), $this->logEvent(),
    $this->onOverdue() (global $cfg reads, alert-recipient resolution,
    email dispatch), $this->getDueDate()/$this->getSLADueDate() (SLA
    schedule resolution) -- stays on the Ticket instance and is invoked
    through it rather than reimplemented here.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once INCLUDE_DIR.'class.orm.php';
include_once INCLUDE_DIR.'class.misc.php';

class TicketOverdueTransitions {

    // Mirrors Ticket::isOverdue() exactly: returns the raw stored
    // 'isoverdue' value from the ticket's ORM hashtable, untouched (no
    // bool cast, no recompute).
    static function isOverdue($ticket) {
        return $ticket->ht['isoverdue'];
    }

    // Mirrors Ticket::markOverdue($whine) exactly.
    static function markOverdue($ticket, $whine=true) {
        // Only open tickets can be marked overdue
        if (!$ticket->isOpen())
            return false;

        if (self::isOverdue($ticket))
            return true;

        $ticket->isoverdue = 1;
        if (!$ticket->save())
            return false;

        $ticket->logEvent('overdue');
        $ticket->onOverdue($whine);

        return true;
    }

    // Mirrors Ticket::clearOverdue($save) exactly.
    static function clearOverdue($ticket, $save=true) {

        //NOTE: Previously logged overdue event is NOT annuled.
        if (self::isOverdue($ticket))
            $ticket->isoverdue = 0;

        // clear due date if it's in the past
        if ($ticket->getDueDate() && Misc::db2gmtime($ticket->getDueDate()) <= Misc::gmtime())
            $ticket->duedate = null;

        // Clear SLA if est. due date is in the past
        if ($ticket->getSLADueDate() && Misc::db2gmtime($ticket->getSLADueDate()) <= Misc::gmtime())
            $ticket->est_duedate = null;

        return $save ? $ticket->save() : true;
    }

    // Mirrors static Ticket::checkOverdue() exactly, including the
    // duedate-over-est_duedate filter precedence and the 100-row cap.
    // Calls this class's own markOverdue() (rather than
    // $ticket->markOverdue()) for each selected ticket, since
    // checkOverdue() and markOverdue() are both entry points being
    // extracted together.
    static function checkOverdue($ticketClass='Ticket') {
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
            self::markOverdue($ticket);
    }
}
