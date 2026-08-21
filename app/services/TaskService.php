<?php

declare(strict_types=1);

require_once __DIR__ . '/../task_policy.php';

final class TaskService
{
    private const TRANSITIONS = [
        'NEW' => ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED'],
        'ASSIGNED' => ['IN_PROGRESS', 'BLOCKED', 'CANCELLED'],
        'IN_PROGRESS' => ['BLOCKED', 'DONE', 'CANCELLED'],
        'BLOCKED' => ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED'],
        'DONE' => [],
        'CANCELLED' => [],
    ];

    public static function create(PDO $db, array $data, int $actorUserId): int
    {
        $taskNo = trim((string)($data['task_no'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if ($taskNo === '' || $title === '') {
            throw new InvalidArgumentException('Task number and title are required.');
        }
        $priority = (string)($data['priority'] ?? 'P3');
        if (!in_array($priority, ['P1', 'P2', 'P3', 'P4'], true)) {
            throw new InvalidArgumentException('Invalid task priority.');
        }

        $stmt = $db->prepare('INSERT INTO tasks
            (task_no,ticket_id,title,description,priority,status,assignee_user_id,created_by_user_id,due_at,created_at,updated_at)
            VALUES(:task_no,:ticket_id,:title,:description,:priority,:status,:assignee_user_id,:created_by_user_id,:due_at,:created_at,:updated_at)');
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            ':task_no' => $taskNo,
            ':ticket_id' => $data['ticket_id'] ?? null,
            ':title' => $title,
            ':description' => $data['description'] ?? null,
            ':priority' => $priority,
            ':status' => $data['status'] ?? 'NEW',
            ':assignee_user_id' => $data['assignee_user_id'] ?? null,
            ':created_by_user_id' => $actorUserId,
            ':due_at' => $data['due_at'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $taskId = (int)$db->lastInsertId();
        self::history($db, $taskId, $actorUserId, 'CREATED', $data['status'] ?? 'NEW', 'Task created');
        if (!empty($data['assignee_user_id'])) {
            self::history($db, $taskId, $actorUserId, 'ASSIGNED', (string)$data['assignee_user_id'], 'Task assigned on creation');
        }
        return $taskId;
    }

    public static function createForTicketAssignment(PDO $db, int $ticketId, int $assigneeUserId, int $actorUserId, array $policy): ?int
    {
        if (!TaskPolicy::shouldCreateOnAssignment($policy, $ticketId, $assigneeUserId)) {
            return null;
        }

        $stmt = $db->prepare('SELECT ticket_no,subject,description,priority FROM tickets WHERE id=:id');
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            throw new RuntimeException('Ticket not found.');
        }

        $taskNo = 'TSK-' . $ticket['ticket_no'] . '-' . date('YmdHis');
        return self::create($db, [
            'task_no' => $taskNo,
            'ticket_id' => $ticketId,
            'title' => 'Support: ' . $ticket['subject'],
            'description' => $ticket['description'],
            'priority' => $ticket['priority'],
            'status' => 'ASSIGNED',
            'assignee_user_id' => $assigneeUserId,
        ], $actorUserId);
    }

    public static function assign(PDO $db, int $taskId, int $assigneeUserId, int $actorUserId): void
    {
        if ($taskId <= 0 || $assigneeUserId <= 0) {
            throw new InvalidArgumentException('Invalid task or assignee.');
        }
        $stmt = $db->prepare("UPDATE tasks SET assignee_user_id=:assignee, status=CASE WHEN status='NEW' THEN 'ASSIGNED' ELSE status END, updated_at=:updated WHERE id=:id");
        $stmt->execute([':assignee' => $assigneeUserId, ':updated' => date('Y-m-d H:i:s'), ':id' => $taskId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Task not found.');
        }
        self::history($db, $taskId, $actorUserId, 'ASSIGNED', (string)$assigneeUserId, 'Task assigned');
    }

    public static function transition(PDO $db, int $taskId, string $newStatus, int $actorUserId): void
    {
        $stmt = $db->prepare('SELECT status FROM tasks WHERE id=:id FOR UPDATE');
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            throw new RuntimeException('Task not found.');
        }
        if (!isset(self::TRANSITIONS[$task['status']]) || !in_array($newStatus, self::TRANSITIONS[$task['status']], true)) {
            throw new InvalidArgumentException("Invalid task transition {$task['status']} -> {$newStatus}.");
        }

        $startedAt = $newStatus === 'IN_PROGRESS' ? date('Y-m-d H:i:s') : null;
        $completedAt = $newStatus === 'DONE' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("UPDATE tasks SET status=:status, started_at=COALESCE(started_at,:started_at), completed_at=:completed_at, updated_at=:updated WHERE id=:id");
        $stmt->execute([
            ':status' => $newStatus,
            ':started_at' => $startedAt,
            ':completed_at' => $completedAt,
            ':updated' => date('Y-m-d H:i:s'),
            ':id' => $taskId,
        ]);
        self::history($db, $taskId, $actorUserId, 'STATUS_CHANGED', $newStatus, 'Task status changed');
    }

    public static function get(PDO $db, int $taskId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM tasks WHERE id=:id');
        $stmt->execute([':id' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function history(PDO $db, int $taskId, int $userId, string $event, ?string $value, ?string $note): void
    {
        $stmt = $db->prepare('INSERT INTO task_history(task_id,user_id,event,value,note,created_at) VALUES(:task_id,:user_id,:event,:value,:note,:created_at)');
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
            ':event' => $event,
            ':value' => $value,
            ':note' => $note,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
