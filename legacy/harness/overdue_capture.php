<?php
require_once __DIR__ . '/../../main.inc.php';

/**
 * Parity harness for the MOD-27 overdue/escalation seam:
 * Ticket::isOverdue(), markOverdue(), clearOverdue(), checkOverdue().
 *
 * Input: {
 *   "action": "isOverdue"|"markOverdue"|"clearOverdue"|"checkOverdue",
 *   "ticket_id": int,
 *   "status_state"?: "open"|"closed"   // seed via setStatusId() before the call
 *   "isoverdue"?: 0|1,
 *   "duedate"?: "Y-m-d H:i:s"|null,
 *   "est_duedate"?: "Y-m-d H:i:s"|null,
 *   "whine"?: bool,                    // markOverdue($whine)
 *   "save"?: bool                      // clearOverdue($save)
 * }
 */

$input = json_decode($argv[1], true);
$action = $input['action'] ?? null;
$ticketId = isset($input['ticket_id']) ? (int) $input['ticket_id'] : null;

$ticket = $ticketId ? Ticket::lookup($ticketId) : null;
if (!$ticket) {
    echo json_encode(['input' => $input, 'output' => null, 'error' => "ticket not found: $ticketId"]);
    exit;
}

// Seed fixture state on the real facade object before exercising the method.
if (isset($input['status_state'])) {
    $status = TicketStatus::objects()->filter(array('state' => $input['status_state']))->first();
    if ($status) {
        $ticket->setStatusId($status->getId());
    }
}
if (array_key_exists('isoverdue', $input)) {
    $ticket->isoverdue = $input['isoverdue'];
}
if (array_key_exists('duedate', $input)) {
    $ticket->duedate = $input['duedate'];
}
if (array_key_exists('est_duedate', $input)) {
    $ticket->est_duedate = $input['est_duedate'];
}
$ticket->save();

$result = null;

switch ($action) {
    case 'isOverdue':
        $result = array('return' => (bool) $ticket->isOverdue());
        break;

    case 'markOverdue':
        $whine = array_key_exists('whine', $input) ? $input['whine'] : true;
        $return = $ticket->markOverdue($whine);
        $result = array(
            'return' => $return,
            'isoverdue' => (int) $ticket->isOverdue(),
            'duedate' => $ticket->getDueDate(),
            'est_duedate' => $ticket->getSLADueDate(),
        );
        break;

    case 'clearOverdue':
        $save = array_key_exists('save', $input) ? $input['save'] : true;
        $return = $ticket->clearOverdue($save);
        // Report the in-memory instance, not a re-fetch — clearOverdue($save=false)
        // must mutate the object without persisting, per the manifest contract.
        $result = array(
            'return' => $return,
            'isoverdue' => (int) $ticket->isOverdue(),
            'duedate' => $ticket->getDueDate(),
            'est_duedate' => $ticket->getSLADueDate(),
        );
        break;

    case 'checkOverdue':
        Ticket::checkOverdue();
        // Bypass ORM identity-map caching — read the batch effect straight from
        // the DB so the harness reflects what checkOverdue() actually persisted.
        $res = db_query('SELECT isoverdue, duedate, est_duedate FROM ' . TICKET_TABLE
            . ' WHERE ticket_id=' . db_input($ticketId));
        $row = $res ? db_fetch_array($res) : null;
        $result = array(
            'isoverdue' => $row ? (int) $row['isoverdue'] : null,
            'duedate' => $row ? $row['duedate'] : null,
            'est_duedate' => $row ? $row['est_duedate'] : null,
        );
        break;

    default:
        $result = array('error' => "unknown action: $action");
}

// Encode the structured result as a JSON string so it round-trips through the
// orchestrator's `output: string | null` fixture contract for exact-match comparison.
echo json_encode(array('input' => $input, 'output' => json_encode($result)));
