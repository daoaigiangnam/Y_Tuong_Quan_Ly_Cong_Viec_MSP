<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/ChangeService.php';
require_once __DIR__ . '/../app/services/ProblemService.php';

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
    $db->exec("INSERT INTO customers(code,name,email,status,created_at) VALUES('TEST-CHANGE-CUSTOMER','Change Test Customer','change@example.com','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='TEST-CHANGE-CUSTOMER'")->fetchColumn();
    }

    $db->exec("INSERT INTO services(code,name,is_active) VALUES('TEST-CHANGE-SVC','Change Test Service',1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $serviceId = (int)$db->lastInsertId();
    if ($serviceId === 0) {
        $serviceId = (int)$db->query("SELECT id FROM services WHERE code='TEST-CHANGE-SVC'")->fetchColumn();
    }

    $roleId = (int)$db->query("SELECT id FROM roles WHERE code='IT_OWNER' LIMIT 1")->fetchColumn();
    if ($roleId < 1) {
        throw new RuntimeException('IT_OWNER role is required for Change integration test.');
    }

    $db->exec("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES('change-test-user','x','Change Test User','change-test@example.com',{$roleId},1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $userId = (int)$db->lastInsertId();
    if ($userId === 0) {
        $userId = (int)$db->query("SELECT id FROM users WHERE username='change-test-user'")->fetchColumn();
    }

    $db->exec("INSERT INTO tickets(ticket_no,customer_id,service_id,subject,description,priority,status,created_by_user_id,created_at,updated_at) VALUES('TCK-CHG-TEST',{$customerId},{$serviceId},'Firewall maintenance','Ticket linked to Change integration test','P2','NEW',{$userId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ticketId = (int)$db->lastInsertId();
    if ($ticketId === 0) {
        $ticketId = (int)$db->query("SELECT id FROM tickets WHERE ticket_no='TCK-CHG-TEST'")->fetchColumn();
    }

    $problemId = ProblemService::create($db, [
        'problem_no' => 'PRB-CHG-TEST',
        'title' => 'Firewall firmware issue',
        'description' => 'Recurring firmware instability requires a controlled change.',
        'problem_type' => 'REACTIVE',
        'priority' => 'P2',
        'customer_id' => $customerId,
        'service_id' => $serviceId,
        'owner_user_id' => $userId,
    ], $userId);

    $changeId = ChangeService::create($db, [
        'change_no' => 'CHG-TEST-001',
        'title' => 'Upgrade firewall firmware',
        'description' => 'Upgrade the firewall firmware to the approved stable release.',
        'change_type' => 'NORMAL',
        'priority' => 'P2',
        'risk' => 'HIGH',
        'impact' => 'MEDIUM',
        'customer_id' => $customerId,
        'service_id' => $serviceId,
        'requester_user_id' => $userId,
        'owner_user_id' => $userId,
        'implementation_plan' => 'Backup configuration, upgrade firmware and validate HA state.',
        'rollback_plan' => 'Restore the previous firmware and configuration if validation fails.',
        'test_plan' => 'Validate HA, routing, VPN and monitoring.',
        'success_criteria' => 'All critical services are healthy and monitoring is green.',
        'reason' => 'Resolve recurring firmware instability.',
    ], $userId);

    ChangeService::linkTicket($db, $changeId, $ticketId, $userId);
    ChangeService::linkProblem($db, $changeId, $problemId, $userId);
    ChangeService::updatePlan($db, $changeId, [
        'test_plan' => 'Validate HA, routing, VPN, monitoring and customer-facing service checks.',
    ], $userId);

    foreach (['ASSESSING', 'PENDING_APPROVAL'] as $status) {
        ChangeService::transition($db, $changeId, $status, $userId);
    }

    ChangeService::approve($db, $changeId, $userId);

    foreach (['SCHEDULED', 'IMPLEMENTING', 'VALIDATING', 'COMPLETED', 'CLOSED'] as $status) {
        ChangeService::transition($db, $changeId, $status, $userId);
    }

    $change = $db->query('SELECT * FROM changes WHERE id=' . $changeId)->fetch();
    if (!$change || $change['status'] !== 'CLOSED' || $change['approver_user_id'] != $userId) {
        throw new RuntimeException('Change lifecycle or approval persistence failed.');
    }
    if ($change['actual_start_at'] === null || $change['actual_end_at'] === null || $change['closed_at'] === null) {
        throw new RuntimeException('Change execution timestamps were not persisted.');
    }

    $ticketLinks = (int)$db->query('SELECT COUNT(*) FROM change_tickets WHERE change_id=' . $changeId . ' AND ticket_id=' . $ticketId)->fetchColumn();
    if ($ticketLinks !== 1) {
        throw new RuntimeException('Change-Ticket relationship was not persisted.');
    }

    $problemLinks = (int)$db->query('SELECT COUNT(*) FROM change_problems WHERE change_id=' . $changeId . ' AND problem_id=' . $problemId)->fetchColumn();
    if ($problemLinks !== 1) {
        throw new RuntimeException('Change-Problem relationship was not persisted.');
    }

    $history = (int)$db->query('SELECT COUNT(*) FROM change_history WHERE change_id=' . $changeId)->fetchColumn();
    if ($history < 10) {
        throw new RuntimeException('Change history is incomplete.');
    }

    $db->rollBack();
    echo "Change MySQL integration tests passed\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
