<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/contract_policy.php';
require_once __DIR__ . '/../app/services/ContractService.php';
require_once __DIR__ . '/../app/services/ContractAlertService.php';

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
    $db->exec("INSERT INTO roles(code,name) VALUES('TEST_ALERT_ROLE','Test Alert Role') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $roleId = (int)$db->lastInsertId();
    if ($roleId === 0) {
        $roleId = (int)$db->query("SELECT id FROM roles WHERE code='TEST_ALERT_ROLE'")->fetchColumn();
    }

    $users = [];
    foreach ([
        ['TEST-ALERT-OWNER','Alert Owner','owner@example.test'],
        ['TEST-ALERT-LEAD','Alert Lead','lead@example.test'],
        ['TEST-ALERT-SALES','Alert Sales','sales@example.test'],
    ] as [$username,$name,$email]) {
        $stmt = $db->prepare('INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES(?,?,?,?,1,1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),email=VALUES(email)');
        $stmt->execute([$username,'test-hash',$name,$email]);
        $id = (int)$db->lastInsertId();
        if ($id === 0) {
            $id = (int)$db->query("SELECT id FROM users WHERE username=" . $db->quote($username))->fetchColumn();
        }
        $users[] = $id;
    }

    $db->exec("INSERT INTO customers(code,name,email,status,created_at) VALUES('TEST-ALERT-CUSTOMER','Alert Test Customer','customer@example.test','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),email=VALUES(email)");
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='TEST-ALERT-CUSTOMER'")->fetchColumn();
    }

    $db->exec("INSERT INTO services(code,name,is_active) VALUES('TEST-ALERT-SVC','Alert Test Service',1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $serviceId = (int)$db->lastInsertId();
    if ($serviceId === 0) {
        $serviceId = (int)$db->query("SELECT id FROM services WHERE code='TEST-ALERT-SVC'")->fetchColumn();
    }

    $contractId = ContractService::create($db, [
        'customer_id' => $customerId,
        'contract_type' => 'FULL_PACKAGE',
        'start_date' => '2026-01-01',
        'end_date' => '2026-11-18',
        'service_ids' => [$serviceId],
        'owner_user_id' => $users[0],
        'lead_user_id' => $users[1],
        'sales_user_id' => $users[2],
    ]);

    ContractService::transition($db, $contractId, 'PENDING_SIGN');
    ContractService::transition($db, $contractId, 'ACTIVE');

    $due = ContractAlertService::planDueAlerts($db, new DateTimeImmutable('2026-08-20'));
    if (count($due) !== 1 || (int)$due[0]['alert_no'] !== 1 || (int)$due[0]['days_before'] !== 90) {
        throw new RuntimeException('Expected exactly alert #1 at 90 days before expiry.');
    }

    if ($due[0]['customer_email'] !== 'customer@example.test') {
        throw new RuntimeException('Customer email routing was not resolved.');
    }
    if ($due[0]['owner_email'] !== 'owner@example.test' || $due[0]['lead_email'] !== 'lead@example.test' || $due[0]['sales_email'] !== 'sales@example.test') {
        throw new RuntimeException('Internal recipient routing was not resolved.');
    }

    $alertId = (int)$due[0]['id'];
    $db->prepare('UPDATE contract_alerts SET sent_at=?,status=? WHERE id=?')->execute(['2026-08-20 09:00:00','SENT',$alertId]);
    $afterSent = ContractAlertService::planDueAlerts($db, new DateTimeImmutable('2026-08-20'));
    if ($afterSent !== []) {
        throw new RuntimeException('Sent alert was planned again; idempotency failed.');
    }

    $db->prepare('UPDATE contract_alerts SET sent_at=NULL,status=?,attempted_at=? WHERE id=?')->execute(['FAILED','2026-08-20 10:00:00',$alertId]);
    $sameDayRetry = ContractAlertService::planDueAlerts($db, new DateTimeImmutable('2026-08-20'));
    if ($sameDayRetry !== []) {
        throw new RuntimeException('Failed alert was retried twice on the same day.');
    }

    $nextDayRetry = ContractAlertService::planDueAlerts($db, new DateTimeImmutable('2026-08-21'));
    if (count($nextDayRetry) !== 1) {
        throw new RuntimeException('Failed alert was not eligible for later retry.');
    }

    $db->rollBack();
    echo "Contract alert MySQL integration tests passed\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
