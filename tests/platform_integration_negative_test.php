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

// The CI seed contains reference data but intentionally does not create users.
// Make this negative-path test self-contained by creating a deterministic test actor.
$actorUsername = 'ci_negative_actor';
$actorRoleId = (int)$db->query("SELECT id FROM roles WHERE code='IT_SUPPORT' LIMIT 1")->fetchColumn();
if ($actorRoleId <= 0) {
    throw new RuntimeException('IT_SUPPORT role is not available for the negative-path test.');
}

$actorStmt = $db->prepare(
    "INSERT INTO users (username, password_hash, full_name, email, role_id, is_active, created_at)
     VALUES (:username, :password_hash, :full_name, :email, :role_id, 1, NOW())
     ON DUPLICATE KEY UPDATE role_id=VALUES(role_id), is_active=1"
);
$actorStmt->execute([
    'username' => $actorUsername,
    'password_hash' => str_repeat('x', 60),
    'full_name' => 'CI Negative Test Actor',
    'email' => 'ci-negative-actor@example.invalid',
    'role_id' => $actorRoleId,
]);

$actorStmt = $db->prepare('SELECT id FROM users WHERE username=:username AND is_active=1 LIMIT 1');
$actorStmt->execute(['username' => $actorUsername]);
$actorId = (int)$actorStmt->fetchColumn();
if ($actorId <= 0) {
    throw new RuntimeException('Unable to create an active test actor.');
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
