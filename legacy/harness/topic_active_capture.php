<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$topic = Topic::create();
$topic->flags = $input['flags'];
$result = [
    'isActive' => $topic->isActive(),
    'isEnabled' => $topic->isEnabled(),
];

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
