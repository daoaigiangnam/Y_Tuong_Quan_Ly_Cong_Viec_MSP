<?php

declare(strict_types=1);

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
    $db->exec("INSERT INTO customers(code,name,email,status,created_at) VALUES('TEST-PROBLEM-CUSTOMER','Problem Test Customer','problem@example.com','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='TEST-PROBLEM-CUSTOMER'")->fetchColumn();
    }

    $db->exec("INSERT INTO services(code,name,is_active) VALUES('TEST-PROBLEM-SVC','Problem Test Service',1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $serviceId = (int)$db->lastInsertId();
    if ($serviceId === 0) {
        $serviceId = (int)$db->query("SELECT id FROM services WHERE code='TEST-PROBLEM-SVC'")->fetchColumn();
    }

    $roleId = (int)$db->query("SELECT id FROM roles WHERE code='IT_OWNER' LIMIT 1")->fetchColumn();
    $db->exec("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES('problem-test-user','x','Problem Test User','problem-test@example.com',{$roleId},1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $userId = (int)$db->lastInsertId();
    if ($userId === 0) {
        $userId = (int)$db->query("SELECT id FROM users WHERE username='problem-test-user'")->fetchColumn();
    }

    $db->exec("INSERT INTO tickets(ticket_no,customer_id,service_id,subject,description,priority,status,created_by_user_id,created_at,updated_at) VALUES('TCK-PRB-TEST',{$customerId},{$serviceId},'Recurring outage','Recurring outage used by Problem integration test','P2','NEW',{$userId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ticketId = (int)$db->lastInsertId();
    if ($ticketId === 0) {
        $ticketId = (int)$db->query("SELECT id FROM tickets WHERE ticket_no='TCK-PRB-TEST'")->fetchColumn();
    }

    $problemId = ProblemService::create($db, [
        'problem_no' => 'PRB-TEST-001',
        'title' => 'Recurring server outage',
        'description' => 'Repeated server restart affecting the service.',
        'problem_type' => 'REACTIVE',
        'priority' => 'P2',
        'customer_id' => $customerId,
        'service_id' => $serviceId,
        'owner_user_id' => $userId,
        'impact_summary' => 'Service interruption during peak hours.',
    ], $userId);

    ProblemService::linkTicket($db, $problemId, $ticketId, $userId);
    ProblemService::updateAnalysis($db, $problemId, [
        'root_cause' => 'Memory exhaustion caused by a runaway process.',
        'workaround' => 'Restart the affected service and stop the runaway process.',
        'permanent_fix' => 'Deploy process limits and fix the memory leak.',
        'change_reference' => 'CHG-PRB-001',
    ], $userId);

    foreach (['ASSESSING', 'INVESTIGATING', 'ROOT_CAUSE_IDENTIFIED', 'FIX_PLANNED', 'FIX_IMPLEMENTED', 'VALIDATING', 'RESOLVED', 'CLOSED'] as $status) {
        ProblemService::transition($db, $problemId, $status, $userId);
    }

    $documentId = ProblemService::addDocument($db, $problemId, [
        'original_name' => 'root-cause-analysis.pdf',
        'storage_key' => 'problems/PRB-TEST-001/root-cause-analysis.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 12345,
        'category' => 'ROOT_CAUSE_ANALYSIS',
        'visibility' => 'INTERNAL_ONLY',
    ], $userId);

    $problem = $db->query('SELECT * FROM problems WHERE id=' . $problemId)->fetch();
    if (!$problem || $problem['status'] !== 'CLOSED' || $problem['root_cause'] === null) {
        throw new RuntimeException('Problem lifecycle or analysis persistence failed.');
    }

    $links = (int)$db->query('SELECT COUNT(*) FROM problem_tickets WHERE problem_id=' . $problemId . ' AND ticket_id=' . $ticketId)->fetchColumn();
    if ($links !== 1) {
        throw new RuntimeException('Problem-Ticket relationship was not persisted.');
    }

    $documents = (int)$db->query('SELECT COUNT(*) FROM problem_documents WHERE id=' . $documentId . ' AND problem_id=' . $problemId . ' AND visibility=\'INTERNAL_ONLY\'')->fetchColumn();
    if ($documents !== 1) {
        throw new RuntimeException('Problem document metadata was not persisted.');
    }

    $history = (int)$db->query('SELECT COUNT(*) FROM problem_history WHERE problem_id=' . $problemId)->fetchColumn();
    if ($history < 10) {
        throw new RuntimeException('Problem history is incomplete.');
    }

    $db->rollBack();
    echo "Problem MySQL integration tests passed\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
