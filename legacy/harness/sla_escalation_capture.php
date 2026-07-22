<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);

if (isset($input['sla_id'])) {
    $sla = SLA::lookup($input['sla_id']);
} else {
    $sla = new SLA();
}

$sla->flags = $input['flags'];
$result = $sla->priorityEscalation();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
