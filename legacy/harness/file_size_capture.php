<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$result = Format::file_size($input['bytes']);

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
