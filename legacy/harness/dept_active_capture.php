<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$dept = new Dept(array('flags' => $input['flags']));
$result = $dept->isActive();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
