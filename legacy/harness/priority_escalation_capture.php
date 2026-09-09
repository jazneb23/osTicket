<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$flags = (int) $input['flags'];

$sla = SLA::create(array(
    'name' => 'harness-pe-' . uniqid('', true),
    'grace_period' => 24,
    'flags' => $flags,
));
$sla->save();
$sla = SLA::lookup($sla->getId());
$result = $sla->priorityEscalation();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
