<?php

declare(strict_types=1);

require_once __DIR__ . '/../problem_policy.php';

final class ProblemService
{
    private static function beginUnitOfWork(PDO $db): ?string
    {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            return null;
        }

        $savepoint = 'problem_sp_' . bin2hex(random_bytes(4));
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
        $db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $db->exec('RELEASE SAVEPOINT ' . $savepoint);
    }

    public static function create(PDO $db, array $data, int $createdByUserId): int
    {
        $errors = validate_problem_payload($data);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid problem: ' . implode(' ', $errors));
        }
        if ($createdByUserId < 1) {
            throw new InvalidArgumentException('Created-by user is required.');
        }

        $now = date('Y-m-d H:i:s');
        $number = trim((string)($data['problem_no'] ?? ''));
        if ($number === '') {
            $number = 'PRB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare(
                'INSERT INTO problems(problem_no,title,description,problem_type,priority,status,customer_id,service_id,owner_user_id,lead_user_id,impact_summary,discovered_at,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $number,
                trim((string)$data['title']),
                trim((string)$data['description']),
                strtoupper((string)$data['problem_type']),
                strtoupper((string)($data['priority'] ?? 'P3')),
                'NEW',
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                !empty($data['service_id']) ? (int)$data['service_id'] : null,
                !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null,
                !empty($data['lead_user_id']) ? (int)$data['lead_user_id'] : null,
                $data['impact_summary'] ?? null,
                $data['discovered_at'] ?? null,
                $createdByUserId,
                $now,
                $now,
            ]);
            $id = (int)$db->lastInsertId();

            self::history($db, $id, $createdByUserId, 'CREATED', 'NEW', null);
            self::commitUnitOfWork($db, $savepoint);
            return $id;
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function transition(PDO $db, int $problemId, string $to, int $userId): void
    {
        $to = strtoupper(trim($to));
        if (!in_array($to, problem_statuses(), true)) {
            throw new InvalidArgumentException('Invalid problem status.');
        }
        if ($problemId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Problem and user are required.');
        }

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare('SELECT status FROM problems WHERE id=? FOR UPDATE');
            $stmt->execute([$problemId]);
            $problem = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$problem) {
                throw new RuntimeException('Problem not found.');
            }

            $from = strtoupper((string)$problem['status']);
            if (!problem_transition_allowed($from, $to)) {
                throw new RuntimeException("Invalid problem transition {$from} -> {$to}");
            }

            $now = date('Y-m-d H:i:s');
            $resolvedAt = in_array($to, ['RESOLVED'], true) ? $now : null;
            $closedAt = $to === 'CLOSED' ? $now : null;
            $rootCauseAt = $to === 'ROOT_CAUSE_IDENTIFIED' ? $now : null;

            $update = $db->prepare(
                'UPDATE problems SET status=?, root_cause_identified_at=COALESCE(root_cause_identified_at,?), resolved_at=COALESCE(?,resolved_at), closed_at=COALESCE(?,closed_at), updated_at=? WHERE id=?'
            );
            $update->execute([$to, $rootCauseAt, $resolvedAt, $closedAt, $now, $problemId]);
            self::history($db, $problemId, $userId, 'STATUS_CHANGED', $to, "{$from} -> {$to}");

            self::commitUnitOfWork($db, $savepoint);
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function linkTicket(PDO $db, int $problemId, int $ticketId, int $userId): void
    {
        if ($problemId < 1 || $ticketId < 1 || $userId < 1) {
            throw new InvalidArgumentException('Problem, ticket and user are required.');
        }

        $stmt = $db->prepare('INSERT INTO problem_tickets(problem_id,ticket_id,linked_by_user_id,linked_at) VALUES(?,?,?,?)');
        $stmt->execute([$problemId, $ticketId, $userId, date('Y-m-d H:i:s')]);
        self::history($db, $problemId, $userId, 'TICKET_LINKED', (string)$ticketId, null);
    }

    public static function updateAnalysis(PDO $db, int $problemId, array $data, int $userId): void
    {
        $allowed = [
            'impact_summary',
            'root_cause',
            'workaround',
            'permanent_fix',
            'change_reference',
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
            throw new InvalidArgumentException('No analysis fields supplied.');
        }

        $values[] = date('Y-m-d H:i:s');
        $values[] = $problemId;
        $stmt = $db->prepare('UPDATE problems SET ' . implode(',', $sets) . ',updated_at=? WHERE id=?');
        $stmt->execute($values);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Problem not found or unchanged.');
        }
        self::history($db, $problemId, $userId, 'ANALYSIS_UPDATED', null, null);
    }

    public static function addDocument(PDO $db, int $problemId, array $data, int $userId): int
    {
        $name = trim((string)($data['original_name'] ?? ''));
        $storageKey = trim((string)($data['storage_key'] ?? ''));
        $visibility = strtoupper(trim((string)($data['visibility'] ?? 'INTERNAL_ONLY')));
        if ($problemId < 1 || $userId < 1 || $name === '' || $storageKey === '') {
            throw new InvalidArgumentException('Problem, user, filename and storage key are required.');
        }
        if (!in_array($visibility, ['INTERNAL_ONLY', 'CUSTOMER_VISIBLE'], true)) {
            throw new InvalidArgumentException('Invalid document visibility.');
        }

        $stmt = $db->prepare(
            'INSERT INTO problem_documents(problem_id,original_name,storage_key,mime_type,file_size,category,visibility,uploaded_by_user_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $problemId,
            $name,
            $storageKey,
            $data['mime_type'] ?? null,
            isset($data['file_size']) ? (int)$data['file_size'] : null,
            $data['category'] ?? null,
            $visibility,
            $userId,
            date('Y-m-d H:i:s'),
        ]);
        $documentId = (int)$db->lastInsertId();
        self::history($db, $problemId, $userId, 'DOCUMENT_UPLOADED', (string)$documentId, $visibility);
        return $documentId;
    }

    private static function history(PDO $db, int $problemId, int $userId, string $event, ?string $value, ?string $note): void
    {
        $stmt = $db->prepare('INSERT INTO problem_history(problem_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$problemId, $userId, $event, $value, $note, date('Y-m-d H:i:s')]);
    }
}
