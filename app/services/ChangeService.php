<?php

declare(strict_types=1);

require_once __DIR__ . '/../change_policy.php';

final class ChangeService
{
    private static function beginUnitOfWork(PDO $db): ?string
    {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            return null;
        }

        $savepoint = 'change_sp_' . bin2hex(random_bytes(4));
        $db->exec('SAVEPOINT ' . $savepoint);
        return $savepoint;
    }

    private static function commitUnitOfWork(PDO $db, ?string $savepoint): void
    {
        if ($savepoint === null) {
            $db->commit();
            return;
        }
        $db->exec('RELEASE SAVEPOINT ' . $savepoint);
    }

    private static function rollbackUnitOfWork(PDO $db, ?string $savepoint): void
    {
        if ($savepoint === null) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return;
        }
        if ($db->inTransaction()) {
            $db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
    }

    public static function create(PDO $db, array $data, int $createdByUserId): int
    {
        $errors = validate_change_payload($data);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid change: ' . implode(' ', $errors));
        }
        if ($createdByUserId < 1) {
            throw new InvalidArgumentException('Created-by user is required.');
        }

        $number = trim((string)($data['change_no'] ?? ''));
        if ($number === '') {
            $number = 'CHG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        $now = date('Y-m-d H:i:s');

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare(
                'INSERT INTO changes(change_no,title,description,change_type,priority,risk,impact,status,customer_id,service_id,requester_user_id,owner_user_id,implementation_plan,rollback_plan,test_plan,success_criteria,reason,planned_start_at,planned_end_at,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $number,
                trim((string)$data['title']),
                trim((string)$data['description']),
                strtoupper((string)$data['change_type']),
                strtoupper((string)($data['priority'] ?? 'P3')),
                strtoupper((string)($data['risk'] ?? 'MEDIUM')),
                strtoupper((string)($data['impact'] ?? 'MEDIUM')),
                'DRAFT',
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                !empty($data['service_id']) ? (int)$data['service_id'] : null,
                !empty($data['requester_user_id']) ? (int)$data['requester_user_id'] : null,
                !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null,
                trim((string)$data['implementation_plan']),
                trim((string)$data['rollback_plan']),
                !empty($data['test_plan']) ? trim((string)$data['test_plan']) : null,
                trim((string)$data['success_criteria']),
                !empty($data['reason']) ? trim((string)$data['reason']) : null,
                $data['planned_start_at'] ?? null,
                $data['planned_end_at'] ?? null,
                $createdByUserId,
                $now,
                $now,
            ]);
            $id = (int)$db->lastInsertId();

            self::history($db, $id, $createdByUserId, 'CREATED', 'DRAFT', null);
            self::commitUnitOfWork($db, $savepoint);
            return $id;
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function transition(PDO $db, int $changeId, string $to, int $userId): void
    {
        $to = strtoupper(trim($to));
        if (!in_array($to, change_statuses(), true)) {
            throw new InvalidArgumentException('Invalid change status.');
        }
        if ($to === 'APPROVED') {
            throw new InvalidArgumentException('Use approve() for APPROVED status.');
        }
        if ($changeId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Change and user are required.');
        }

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare('SELECT status FROM changes WHERE id=? FOR UPDATE');
            $stmt->execute([$changeId]);
            $change = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$change) {
                throw new RuntimeException('Change not found.');
            }

            $from = strtoupper((string)$change['status']);
            if (!change_transition_allowed($from, $to)) {
                throw new RuntimeException("Invalid change transition {$from} -> {$to}");
            }

            $now = date('Y-m-d H:i:s');
            $actualStart = $to === 'IMPLEMENTING' ? $now : null;
            $actualEnd = in_array($to, ['COMPLETED', 'FAILED', 'ROLLED_BACK'], true) ? $now : null;
            $closedAt = $to === 'CLOSED' ? $now : null;

            $update = $db->prepare(
                'UPDATE changes SET status=?, actual_start_at=COALESCE(actual_start_at,?), actual_end_at=COALESCE(?,actual_end_at), closed_at=COALESCE(?,closed_at), updated_at=? WHERE id=?'
            );
            $update->execute([$to, $actualStart, $actualEnd, $closedAt, $now, $changeId]);
            self::history($db, $changeId, $userId, 'STATUS_CHANGED', $to, "{$from} -> {$to}");

            self::commitUnitOfWork($db, $savepoint);
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function approve(PDO $db, int $changeId, int $approverUserId): void
    {
        if ($changeId < 1 || $approverUserId < 1) {
            throw new InvalidArgumentException('Change and approver are required.');
        }

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare('SELECT status FROM changes WHERE id=? FOR UPDATE');
            $stmt->execute([$changeId]);
            $change = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$change) {
                throw new RuntimeException('Change not found.');
            }
            if ((string)$change['status'] !== 'PENDING_APPROVAL') {
                throw new RuntimeException('Only pending changes can be approved.');
            }

            $now = date('Y-m-d H:i:s');
            $update = $db->prepare('UPDATE changes SET status=\'APPROVED\',approver_user_id=?,approved_at=?,updated_at=? WHERE id=?');
            $update->execute([$approverUserId, $now, $now, $changeId]);
            self::history($db, $changeId, $approverUserId, 'APPROVED', 'APPROVED', null);

            self::commitUnitOfWork($db, $savepoint);
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function updatePlan(PDO $db, int $changeId, array $data, int $userId): void
    {
        if ($changeId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Change and user are required.');
        }

        $allowed = [
            'implementation_plan',
            'rollback_plan',
            'test_plan',
            'success_criteria',
            'reason',
            'planned_start_at',
            'planned_end_at',
        ];
        $sets = [];
        $values = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = $field . '=?';
                $values[] = $data[$field] !== null ? trim((string)$data[$field]) : null;
            }
        }
        if ($sets === []) {
            throw new InvalidArgumentException('No change plan fields supplied.');
        }

        $values[] = date('Y-m-d H:i:s');
        $values[] = $changeId;
        $stmt = $db->prepare('UPDATE changes SET ' . implode(',', $sets) . ',updated_at=? WHERE id=?');
        $stmt->execute($values);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Change not found or unchanged.');
        }
        self::history($db, $changeId, $userId, 'PLAN_UPDATED', null, implode(',', array_map(static fn(string $field): string => $field, array_keys(array_intersect_key($data, array_flip($allowed))))));
    }

    public static function linkTicket(PDO $db, int $changeId, int $ticketId, int $userId): void
    {
        if ($changeId < 1 || $ticketId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Change, ticket and user are required.');
        }

        $stmt = $db->prepare('INSERT IGNORE INTO change_tickets(change_id,ticket_id,linked_by_user_id,linked_at) VALUES(?,?,?,?)');
        $stmt->execute([$changeId, $ticketId, $userId, date('Y-m-d H:i:s')]);
        if ($stmt->rowCount() > 0) {
            self::history($db, $changeId, $userId, 'TICKET_LINKED', (string)$ticketId, null);
        }
    }

    public static function linkProblem(PDO $db, int $changeId, int $problemId, int $userId): void
    {
        if ($changeId < 1 || $problemId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Change, problem and user are required.');
        }

        $stmt = $db->prepare('INSERT IGNORE INTO change_problems(change_id,problem_id,linked_by_user_id,linked_at) VALUES(?,?,?,?)');
        $stmt->execute([$changeId, $problemId, $userId, date('Y-m-d H:i:s')]);
        if ($stmt->rowCount() > 0) {
            self::history($db, $changeId, $userId, 'PROBLEM_LINKED', (string)$problemId, null);
        }
    }

    private static function history(PDO $db, int $changeId, int $userId, string $event, ?string $value, ?string $note): void
    {
        $stmt = $db->prepare('INSERT INTO change_history(change_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$changeId, $userId, $event, $value, $note, date('Y-m-d H:i:s')]);
    }
}
