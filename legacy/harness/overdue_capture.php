<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$action = $input['action'];
$ticket = null;

if (in_array($action, ['markOverdue', 'clearOverdue'], true)) {
    $ticket = Ticket::lookup($input['ticket_id']);

    if (isset($input['status_state'])) {
        $status = TicketStatus::objects()
            ->filter(['state' => $input['status_state']])
            ->first();
        if ($status) {
            $ticket->setStatusId($status->getId());
        }
    }

    if (isset($input['isoverdue'])) {
        $ticket->isoverdue = $input['isoverdue'];
    }

    if (array_key_exists('duedate', $input)) {
        $ticket->duedate = $input['duedate'];
    }

    if (array_key_exists('est_duedate', $input)) {
        $ticket->est_duedate = $input['est_duedate'];
    }

    $ticket->save();
}

switch ($action) {
    case 'markOverdue':
        $whine = $input['whine'] ?? true;
        $return = $ticket->markOverdue($whine);
        break;

    case 'clearOverdue':
        $save = $input['save'] ?? true;
        $return = $ticket->clearOverdue($save);
        break;

    case 'checkOverdue':
        Ticket::checkOverdue();
        $return = null;
        if (isset($input['ticket_id'])) {
            $ticket = Ticket::lookup($input['ticket_id']);
        }
        break;

}

$result = [
    'return' => $return,
    'isoverdue' => $ticket ? $ticket->isOverdue() : null,
    'duedate' => $ticket ? $ticket->getDueDate() : null,
    'est_duedate' => $ticket ? $ticket->est_duedate : null,
];

echo json_encode([
    'input' => $input,
    'output' => json_encode($result),
]);
