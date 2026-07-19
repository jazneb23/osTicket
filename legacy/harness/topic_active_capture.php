<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$topic = new Topic(array('flags' => $input['flags']));
$result = $topic->isActive();

if ($topic->isEnabled() !== $result) {
    throw new RuntimeException('isEnabled() must delegate to isActive()');
}

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
