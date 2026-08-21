<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'msp_itsm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$checks = [
    'customer' => "SELECT COUNT(*) FROM customers WHERE code='INT-CUSTOMER'",
    'integration_owner' => "SELECT COUNT(*) FROM users WHERE username='integration-owner'",
    'contract' => "SELECT COUNT(*) FROM contracts WHERE contract_no='INT-CONTRACT'",
    'ticket' => "SELECT COUNT(*) FROM tickets WHERE ticket_no='INT-TICKET'",
    'problem' => "SELECT COUNT(*) FROM problems WHERE problem_no='INT-PROBLEM'",
    'change' => "SELECT COUNT(*) FROM changes WHERE change_no='INT-CHANGE'",
    'cmdb_server' => "SELECT COUNT(*) FROM cmdb_cis WHERE code='INT-CI-01'",
    'cmdb_application' => "SELECT COUNT(*) FROM cmdb_cis WHERE code='INT-CI-02'",
    'knowledge' => "SELECT COUNT(*) FROM knowledge_articles WHERE article_no='INT-KB'",
    'task_for_integration_ticket' => "SELECT COUNT(*) FROM tasks t INNER JOIN tickets ti ON ti.id=t.ticket_id WHERE ti.ticket_no='INT-TICKET'",
];

foreach ($checks as $name => $sql) {
    $count = (int)$db->query($sql)->fetchColumn();
    if ($count !== 0) {
        throw new RuntimeException("Platform integration cleanup assertion failed: {$name} has {$count} residual row(s).");
    }
}

echo "Platform integration rollback/cleanup test passed: no INT-* integration data remains after smoke test rollback.\n";
