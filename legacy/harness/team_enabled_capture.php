<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$team = new Team(array('flags' => $input['flags']));
$result = $team->isEnabled();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
