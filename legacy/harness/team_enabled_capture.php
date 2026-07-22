<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$flags = ($input['enabled'] ? Team::FLAG_ENABLED : 0)
    | ($input['noalerts'] ? Team::FLAG_NOALERTS : 0);
$team = new Team(array('flags' => $flags));
$result = $team->isEnabled();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
