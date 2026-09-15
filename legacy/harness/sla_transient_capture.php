<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$sla = new SLA(array('id' => 1));
$sla->flags = (int) $input['flags'];
$result = $sla->isTransient();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
