<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);

function ensureStatuses() {
    if (!TicketStatus::objects()->count())
        TicketStatusList::__load();
}

function statusForState($state) {
    ensureStatuses();
    return TicketStatus::objects()
        ->filter(array('state' => $state))
        ->order_by('id')
        ->first();
}

function buildTicket(array $fields) {
    $status = statusForState($fields['status_state'] ?? 'open');
    $data = array(
        'isoverdue' => $fields['isoverdue'] ?? 0,
        'status_id' => $status->getId(),
        'status' => $status,
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s'),
    );

    if (array_key_exists('duedate', $fields))
        $data['duedate'] = $fields['duedate'];
    if (array_key_exists('est_duedate', $fields))
        $data['est_duedate'] = $fields['est_duedate'];

    return new Ticket($data);
}

function seedTicketRow(array $fields) {
    $status = statusForState($fields['status_state'] ?? 'open');
    $now = date('Y-m-d H:i:s');
    $data = array(
        'status_id' => $status->getId(),
        'isoverdue' => $fields['isoverdue'] ?? 0,
        'created' => $now,
        'updated' => $now,
    );

    if (array_key_exists('duedate', $fields))
        $data['duedate'] = $fields['duedate'];
    if (array_key_exists('est_duedate', $fields))
        $data['est_duedate'] = $fields['est_duedate'];

    $ticket = new Ticket($data);
    $ticket->save();

    return $ticket->getId();
}

$action = $input['action'] ?? 'isOverdue';

switch ($action) {
case 'isOverdue':
    $ticket = buildTicket($input);
    $result = $ticket->isOverdue();
    break;

case 'markOverdue':
    $ticket = buildTicket($input);
    $whine = array_key_exists('whine', $input) ? (bool) $input['whine'] : true;
    $result = $ticket->markOverdue($whine);
    break;

case 'clearOverdue':
    $ticket = buildTicket($input);
    $save = array_key_exists('save', $input) ? (bool) $input['save'] : true;
    $result = $ticket->clearOverdue($save);
    break;

case 'checkOverdue':
    ensureStatuses();
    db_query('DELETE FROM ' . TICKET_TABLE);
    foreach ($input['tickets'] ?? array() as $row)
        seedTicketRow($row);
    Ticket::checkOverdue();
    $result = null;
    break;

default:
    $result = null;
}

echo json_encode(array(
    'input' => $input,
    'output' => $result,
));
