<?php
require_once __DIR__ . '/../../main.inc.php';

$input = json_decode($argv[1], true);
$flags = ($input['active'] ? Dept::FLAG_ACTIVE : 0)
    | ($input['archived'] ? Dept::FLAG_ARCHIVED : 0)
    | ($input['assignMembersOnly'] ? Dept::FLAG_ASSIGN_MEMBERS_ONLY : 0)
    | ($input['disableAutoClaim'] ? Dept::FLAG_DISABLE_AUTO_CLAIM : 0)
    | ($input['assignPrimaryOnly'] ? Dept::FLAG_ASSIGN_PRIMARY_ONLY : 0)
    | ($input['disableReopenAutoAssign'] ? Dept::FLAG_DISABLE_REOPEN_AUTO_ASSIGN : 0);
$dept = new Dept(array('flags' => $flags));
$result = $dept->isActive();

echo json_encode([
    'input' => $input,
    'output' => $result,
]);
