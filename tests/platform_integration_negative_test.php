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

$actorId = (int)$db->query("SELECT id FROM users WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
if ($actorId <= 0) {
    throw new RuntimeException('No active test actor is available.');
}

$assertions = 0;

// 1. Invalid task priority must be rejected before persistence.
try {
    TaskService::create($db, [
        'task_no' => 'NEG-PRIORITY-' . date('YmdHis'),
        'title' => 'Negative priority test',
        'priority' => 'P0',
    ], $actorId);
    throw new RuntimeException('Negative test failed: invalid priority was accepted.');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() !== 'Invalid task priority.') {
        throw $e;
    }
    $assertions++;
}

// 2. A ticket assignment event for a non-existent ticket must be rejected.
$policy = TaskPolicy::normalize(['enabled' => true, 'trigger_event' => 'TICKET_ASSIGNMENT']);
try {
    TaskService::createForTicketAssignment($db, 999999999, $actorId, $actorId, $policy);
    throw new RuntimeException('Negative test failed: non-existent ticket was accepted.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Ticket not found.') {
        throw $e;
    }
    $assertions++;
}

// 3. Task lifecycle must reject an illegal state transition.
$db->beginTransaction();
try {
    $taskId = TaskService::create($db, [
        'task_no' => 'NEG-TRANSITION-' . date('YmdHis'),
        'title' => 'Negative transition test',
        'priority' => 'P3',
        'status' => 'NEW',
    ], $actorId);

    try {
        TaskService::transition($db, $taskId, 'DONE', $actorId);
        throw new RuntimeException('Negative test failed: NEW -> DONE transition was accepted.');
    } catch (InvalidArgumentException $e) {
        if ($e->getMessage() !== 'Invalid task transition NEW -> DONE.') {
            throw $e;
        }
        $assertions++;
    }

    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

if ($assertions !== 3) {
    throw new RuntimeException("Platform negative-path test failed: expected 3 assertions, got {$assertions}.");
}

echo "Platform negative-path test passed: invalid priority, missing ticket, and illegal task transition are rejected.\n";
