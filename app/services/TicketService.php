<?php

declare(strict_types=1);

require_once __DIR__ . '/../ticket_policy.php';

final class TicketService
{
    public static function create(PDO $db, array $data): int
    {
        $errors = validate_ticket_payload($data);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid ticket: ' . implode(' ', $errors));
        }

        $now = now();
        $number = 'INC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $db->beginTransaction();
        try {
            $s = $db->prepare(
                'INSERT INTO tickets(ticket_no,customer_id,contract_id,service_id,request_type,subject,description,priority,status,owner_user_id,assigned_user_id,created_by_user_id,requester_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $s->execute([
                $number,
                (int)$data['customer_id'],
                !empty($data['contract_id']) ? (int)$data['contract_id'] : null,
                !empty($data['service_id']) ? (int)$data['service_id'] : null,
                !empty($data['request_type']) ? trim((string)$data['request_type']) : null,
                trim((string)$data['subject']),
                trim((string)$data['description']),
                strtoupper((string)$data['priority']),
                'NEW',
                !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null,
                !empty($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null,
                (int)$data['created_by_user_id'],
                !empty($data['requester_user_id']) ? (int)$data['requester_user_id'] : null,
                $now,
                $now,
            ]);
            $id = (int)$db->lastInsertId();
            self::history($db, $id, (int)$data['created_by_user_id'], 'CREATED', 'NEW', null);
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function transition(PDO $db, int $id, string $status, int $userId, ?string $note = null): void
    {
        $db->beginTransaction();
        try {
            $s = $db->prepare('SELECT status,reopen_count FROM tickets WHERE id=? FOR UPDATE');
            $s->execute([$id]);
            $ticket = $s->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                throw new RuntimeException('Ticket not found');
            }

            $from = (string)$ticket['status'];
            $to = strtoupper(trim($status));
            if (!ticket_transition_allowed($from, $to)) {
                throw new RuntimeException("Invalid transition {$from} -> {$to}");
            }
            if ($to === 'REOPENED' && !can_reopen_ticket($from, (string)$note)) {
                throw new InvalidArgumentException('Reopen requires a reason.');
            }

            $reopenCount = (int)$ticket['reopen_count'] + ($to === 'REOPENED' ? 1 : 0);
            $firstResponseSql = ($from === 'NEW' && $to !== 'NEW') ? ', first_response_at = COALESCE(first_response_at, NOW())' : '';
            $sql = 'UPDATE tickets SET status=?, reopen_count=?, resolved_at=IF(?="RESOLVED",COALESCE(resolved_at,NOW()),resolved_at), closed_at=IF(?="CLOSED",COALESCE(closed_at,NOW()),closed_at), updated_at=NOW()' . $firstResponseSql . ' WHERE id=?';
            $s = $db->prepare($sql);
            $s->execute([$to, $reopenCount, $to, $to, $id]);

            self::history($db, $id, $userId, 'STATUS_CHANGED', $to, $note);
            if ($to === 'REOPENED') {
                self::history($db, $id, $userId, 'REOPENED', 'ALERT_REQUIRED', $note);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function history(PDO $db, int $ticketId, int $userId, string $event, string $value, ?string $note): void
    {
        $s = $db->prepare('INSERT INTO ticket_history(ticket_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,?)');
        $s->execute([$ticketId, $userId, $event, $value, $note, now()]);
    }

    public static function comment(PDO $db, int $ticketId, int $userId, string $body, bool $internal = false): void
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Comment body is required.');
        }

        $visibility = $internal ? 'INTERNAL_ONLY' : 'CUSTOMER_VISIBLE';
        $s = $db->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body,visibility,is_internal,created_at) VALUES(?,?,?,?,?,?)');
        $s->execute([$ticketId, $userId, $body, $visibility, $internal ? 1 : 0, now()]);
        self::history($db, $ticketId, $userId, 'COMMENT', $visibility, $body);
    }
}
