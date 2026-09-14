<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$sla = new SLA(array('flags' => $input['flags']));
$result = $sla->isTransient();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
