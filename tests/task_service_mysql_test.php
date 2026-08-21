<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/TaskService.php';
require_once __DIR__ . '/../app/task_policy.php';

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
    $db->exec("INSERT INTO customers(code,name,email,status,created_at) VALUES('TEST-TASK-CUSTOMER','Task Test Customer','task@example.com','ACTIVE',NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='TEST-TASK-CUSTOMER'")->fetchColumn();
    }

    $db->exec("INSERT INTO services(code,name,is_active) VALUES('TEST-TASK-SVC','Task Test Service',1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $serviceId = (int)$db->lastInsertId();
    if ($serviceId === 0) {
        $serviceId = (int)$db->query("SELECT id FROM services WHERE code='TEST-TASK-SVC'")->fetchColumn();
    }

    $roleId = (int)$db->query("SELECT id FROM roles WHERE code='IT_OWNER' LIMIT 1")->fetchColumn();
    if ($roleId === 0) {
        throw new RuntimeException('IT_OWNER role is required for Task integration test.');
    }

    $db->exec("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES('task-test-user','x','Task Test User','task-test@example.com',{$roleId},1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $userId = (int)$db->lastInsertId();
    if ($userId === 0) {
        $userId = (int)$db->query("SELECT id FROM users WHERE username='task-test-user'")->fetchColumn();
    }

    $db->exec("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES('task-test-assignee','x','Task Test Assignee','task-assignee@example.com',{$roleId},1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $assigneeId = (int)$db->lastInsertId();
    if ($assigneeId === 0) {
        $assigneeId = (int)$db->query("SELECT id FROM users WHERE username='task-test-assignee'")->fetchColumn();
    }

    $db->exec("INSERT INTO tickets(ticket_no,customer_id,service_id,subject,description,priority,status,created_by_user_id,created_at,updated_at) VALUES('TCK-TASK-TEST',{$customerId},{$serviceId},'Task integration ticket','Ticket used by Task MySQL integration test','P2','ASSIGNED',{$userId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ticketId = (int)$db->lastInsertId();
    if ($ticketId === 0) {
        $ticketId = (int)$db->query("SELECT id FROM tickets WHERE ticket_no='TCK-TASK-TEST'")->fetchColumn();
    }

    $enabledPolicy = TaskPolicy::normalize(['enabled' => true, 'trigger_event' => 'TICKET_ASSIGNMENT']);
    $taskId = TaskService::createForTicketAssignment($db, $ticketId, $assigneeId, $userId, $enabledPolicy);
    if ($taskId === null) {
        throw new RuntimeException('Enabled assignment policy did not create a Task.');
    }

    $disabledBefore = (int)$db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    $disabledPolicy = TaskPolicy::normalize(['enabled' => false, 'trigger_event' => 'TICKET_ASSIGNMENT']);
    $disabledTaskId = TaskService::createForTicketAssignment($db, $ticketId, $assigneeId, $userId, $disabledPolicy);
    $disabledAfter = (int)$db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    if ($disabledTaskId !== null || $disabledAfter !== $disabledBefore) {
        throw new RuntimeException('Disabled assignment policy created an unexpected Task.');
    }

    $invalidRejected = false;
    try {
        TaskService::create($db, [
            'task_no' => 'TSK-INVALID-STATUS',
            'title' => 'Invalid lifecycle task',
            'status' => 'INVALID_STATUS',
            'assignee_user_id' => $assigneeId,
        ], $userId);
    } catch (InvalidArgumentException $e) {
        $invalidRejected = true;
    }
    if (!$invalidRejected) {
        throw new RuntimeException('Task creation accepted an invalid initial lifecycle state.');
    }

    TaskService::transition($db, $taskId, 'IN_PROGRESS', $assigneeId);
    TaskService::transition($db, $taskId, 'DONE', $assigneeId);

    $task = TaskService::get($db, $taskId);
    if (!$task || $task['status'] !== 'DONE' || (int)$task['ticket_id'] !== $ticketId || (int)$task['assignee_user_id'] !== $assigneeId) {
        throw new RuntimeException('Task lifecycle or persistence failed.');
    }
    if ($task['started_at'] === null || $task['completed_at'] === null) {
        throw new RuntimeException('Task timestamps were not persisted.');
    }
    if ($task['title'] !== 'Support: Task integration ticket' || $task['priority'] !== 'P2') {
        throw new RuntimeException('Ticket-to-Task mapping failed.');
    }

    $history = (int)$db->query('SELECT COUNT(*) FROM task_history WHERE task_id=' . $taskId)->fetchColumn();
    if ($history < 4) {
        throw new RuntimeException('Task history is incomplete.');
    }

    $db->rollBack();
    echo "Task MySQL integration tests passed\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
