<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$flags = ($input['active'] ? Topic::FLAG_ACTIVE : 0)
    | ($input['archived'] ? Topic::FLAG_ARCHIVED : 0)
    | ($input['customNumbers'] ? Topic::FLAG_CUSTOM_NUMBERS : 0);
$topic = new Topic(array('flags' => $flags));
$result = $topic->isActive();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
