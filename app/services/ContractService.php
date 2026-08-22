<?php
declare(strict_types=1);

require_once __DIR__ . '/../contract_policy.php';

final class ContractService
{
    private static int $savepointSequence = 0;

    /**
     * Start an independent transaction when possible; otherwise use a SAVEPOINT
     * so the service can safely be called from an existing application transaction.
     *
     * @return array{0: bool, 1: ?string}
     */
    private static function beginUnit(PDO $db): array
    {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            return [true, null];
        }

        $savepoint = 'contract_sp_' . (++self::$savepointSequence);
        $db->exec('SAVEPOINT ' . $savepoint);

        return [false, $savepoint];
    }

    private static function commitUnit(
        PDO $db,
        bool $ownsTransaction,
        ?string $savepoint
    ): void {
        if ($ownsTransaction) {
            $db->commit();
            return;
        }

        if ($savepoint !== null) {
            $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
    }

    private static function rollbackUnit(
        PDO $db,
        bool $ownsTransaction,
        ?string $savepoint
    ): void {
        if ($ownsTransaction) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return;
        }

        if ($savepoint !== null && $db->inTransaction()) {
            $db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
    }

    public static function create(PDO $db, array $data): int
    {
        $number = trim((string)($data['contract_no'] ?? ''));

        if ($number === '') {
            $number = 'CTR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        [$ownsTransaction, $savepoint] = self::beginUnit($db);

        try {
            $stmt = $db->prepare(
                'INSERT INTO contracts(
                    contract_no,
                    customer_id,
                    contract_type,
                    start_date,
                    end_date,
                    value,
                    status,
                    owner_user_id,
                    lead_user_id,
                    sales_user_id,
                    public_notes,
                    internal_notes,
                    created_at,
                    updated_at
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );

            $stmt->execute([
                $number,
                $data['customer_id'] ?? null,
                $data['contract_type'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['value'] ?? 0,
                $data['status'] ?? 'DRAFT',
                $data['owner_user_id'] ?? null,
                $data['lead_user_id'] ?? null,
                $data['sales_user_id'] ?? null,
                $data['public_notes'] ?? null,
                $data['internal_notes'] ?? null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);

            $id = (int)$db->lastInsertId();

            if (!empty($data['alert_days'])) {
                $ruleStmt = $db->prepare(
                    'INSERT INTO contract_alert_rules(
                        contract_id,
                        alert_no,
                        days_before
                    ) VALUES(?,?,?)'
                );

                foreach ($data['alert_days'] as $alertNo => $daysBefore) {
                    $ruleStmt->execute([$id, $alertNo, $daysBefore]);
                }
            }

            self::commitUnit($db, $ownsTransaction, $savepoint);

            return $id;
        } catch (Throwable $e) {
            self::rollbackUnit($db, $ownsTransaction, $savepoint);
            throw $e;
        }
    }

    public static function transition(PDO $db, int $id, string $to): void
    {
        [$ownsTransaction, $savepoint] = self::beginUnit($db);

        try {
            $stmt = $db->prepare(
                'SELECT status FROM contracts WHERE id=? FOR UPDATE'
            );

            $stmt->execute([$id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Contract not found');
            }

            $from = (string)$row['status'];

            ContractPolicy::assertTransition($from, $to);

            $update = $db->prepare(
                'UPDATE contracts
                 SET status=?, updated_at=?
                 WHERE id=?'
            );

            $update->execute([
                $to,
                date('Y-m-d H:i:s'),
                $id
            ]);

            self::commitUnit($db, $ownsTransaction, $savepoint);
        } catch (Throwable $e) {
            self::rollbackUnit($db, $ownsTransaction, $savepoint);
            throw $e;
        }
    }
}
