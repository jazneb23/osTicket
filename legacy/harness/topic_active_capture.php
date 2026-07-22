<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);

$topic = new Topic();
$topic->flags = $input['flags'];
$result = $topic->isActive();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
