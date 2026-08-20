<?php

declare(strict_types=1);

require_once __DIR__ . '/../contract_policy.php';

final class ContractService
{
    private static function beginUnitOfWork(PDO $db): ?string
    {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            return null;
        }

        $savepoint = 'contract_sp_' . bin2hex(random_bytes(4));
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

    public static function create(PDO $db, array $data): int
    {
        $errors = validate_contract_payload($data);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid contract: ' . implode(' ', $errors));
        }

        $now = date('Y-m-d H:i:s');
        $number = trim((string)($data['contract_no'] ?? ''));
        if ($number === '') {
            $number = 'CTR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare(
                'INSERT INTO contracts(contract_no,customer_id,contract_type,start_date,end_date,value,status,owner_user_id,lead_user_id,sales_user_id,public_notes,internal_notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $number,
                (int)$data['customer_id'],
                strtoupper((string)$data['contract_type']),
                $data['start_date'],
                $data['end_date'],
                $data['value'] ?? null,
                'DRAFT',
                !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null,
                !empty($data['lead_user_id']) ? (int)$data['lead_user_id'] : null,
                !empty($data['sales_user_id']) ? (int)$data['sales_user_id'] : null,
                $data['public_notes'] ?? null,
                $data['internal_notes'] ?? null,
                $now,
                $now,
            ]);
            $id = (int)$db->lastInsertId();

            if (!empty($data['service_ids'])) {
                $link = $db->prepare('INSERT INTO contract_services(contract_id,service_id) VALUES(?,?)');
                foreach (array_unique(array_map('intval', (array)$data['service_ids'])) as $serviceId) {
                    if ($serviceId > 0) {
                        $link->execute([$id, $serviceId]);
                    }
                }
            }

            $rules = !empty($data['alert_rules']) ? $data['alert_rules'] : default_contract_alert_rules();
            $ruleStmt = $db->prepare('INSERT INTO contract_alert_rules(contract_id,alert_no,days_before,is_active) VALUES(?,?,?,1)');
            foreach ($rules as $rule) {
                $alertNo = (int)($rule['alert_no'] ?? 0);
                $daysBefore = (int)($rule['days_before'] ?? -1);
                if ($alertNo < 1 || $daysBefore < 0) {
                    throw new InvalidArgumentException('Invalid contract alert rule.');
                }
                $ruleStmt->execute([$id, $alertNo, $daysBefore]);
            }

            self::commitUnitOfWork($db, $savepoint);
            return $id;
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }

    public static function transition(PDO $db, int $id, string $to): void
    {
        $savepoint = self::beginUnitOfWork($db);
        try {
            $stmt = $db->prepare('SELECT status FROM contracts WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$contract) {
                throw new RuntimeException('Contract not found.');
            }

            $from = strtoupper((string)$contract['status']);
            $to = strtoupper(trim($to));
            if (!contract_transition_allowed($from, $to)) {
                throw new RuntimeException("Invalid contract transition {$from} -> {$to}");
            }

            $update = $db->prepare('UPDATE contracts SET status=?, updated_at=? WHERE id=?');
            $update->execute([$to, date('Y-m-d H:i:s'), $id]);
            self::commitUnitOfWork($db, $savepoint);
        } catch (Throwable $e) {
            self::rollbackUnitOfWork($db, $savepoint);
            throw $e;
        }
    }
}
