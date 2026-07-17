<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$flags = (int) $input['flags'];

if (array_key_exists('transient', $input)) {
    if ($input['transient']) {
        $flags |= SLA::FLAG_TRANSIENT;
    } else {
        $flags &= ~SLA::FLAG_TRANSIENT;
    }
}

$sla = new SLA(['flags' => $flags]);
$result = $sla->isTransient();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
