<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/ContractService.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'msp_itsm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$db->beginTransaction();
try {
    $db->exec("INSERT INTO customers(code,name,status,created_at) VALUES('TEST-CONTRACT-CUSTOMER','Contract Test Customer','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='TEST-CONTRACT-CUSTOMER'")->fetchColumn();
    }

    $db->exec("INSERT INTO services(code,name,is_active) VALUES('TEST-CONTRACT-SVC','Contract Test Service',1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $serviceId = (int)$db->lastInsertId();
    if ($serviceId === 0) {
        $serviceId = (int)$db->query("SELECT id FROM services WHERE code='TEST-CONTRACT-SVC'")->fetchColumn();
    }

    $contractId = ContractService::create($db, [
        'customer_id' => $customerId,
        'contract_type' => 'FULL_PACKAGE',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'service_ids' => [$serviceId],
    ]);

    $contract = $db->query('SELECT * FROM contracts WHERE id=' . $contractId)->fetch();
    if (!$contract || $contract['status'] !== 'DRAFT') {
        throw new RuntimeException('Contract was not created as DRAFT.');
    }

    $rules = $db->query('SELECT alert_no,days_before FROM contract_alert_rules WHERE contract_id=' . $contractId . ' ORDER BY alert_no')->fetchAll();
    if (count($rules) !== 3 || (int)$rules[0]['days_before'] !== 90 || (int)$rules[1]['days_before'] !== 60 || (int)$rules[2]['days_before'] !== 30) {
        throw new RuntimeException('Default 90/60/30 alert rules were not created.');
    }

    $linked = $db->query('SELECT COUNT(*) FROM contract_services WHERE contract_id=' . $contractId . ' AND service_id=' . $serviceId)->fetchColumn();
    if ((int)$linked !== 1) {
        throw new RuntimeException('Contract service relationship was not created.');
    }

    ContractService::transition($db, $contractId, 'PENDING_SIGN');
    ContractService::transition($db, $contractId, 'ACTIVE');

    $status = $db->query('SELECT status FROM contracts WHERE id=' . $contractId)->fetchColumn();
    if ($status !== 'ACTIVE') {
        throw new RuntimeException('Contract lifecycle transition failed.');
    }

    $db->rollBack();
    echo "Contract MySQL integration tests passed\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
