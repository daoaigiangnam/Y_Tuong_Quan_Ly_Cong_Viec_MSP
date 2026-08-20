<?php

declare(strict_types=1);

final class ContractAlertService
{
    public static function planDueAlerts(PDO $db, DateTimeImmutable $today): array
    {
        $todayDate = $today->format('Y-m-d');
        $sql = "SELECT c.id contract_id,c.contract_no,c.customer_id,
                       c.end_date,c.owner_user_id,c.lead_user_id,c.sales_user_id,
                       cu.name customer_name,cu.email customer_email,
                       uo.email owner_email,ul.email lead_email,us.email sales_email,
                       r.alert_no,r.days_before
                FROM contracts c
                JOIN customers cu ON cu.id=c.customer_id
                LEFT JOIN users uo ON uo.id=c.owner_user_id
                LEFT JOIN users ul ON ul.id=c.lead_user_id
                LEFT JOIN users us ON us.id=c.sales_user_id
                JOIN contract_alert_rules r ON r.contract_id=c.id AND r.is_active=1
                WHERE c.status IN ('ACTIVE','EXPIRING')
                  AND c.end_date >= ?
                  AND DATE_SUB(c.end_date, INTERVAL r.days_before DAY) <= ?
                ORDER BY c.end_date,r.alert_no";

        $stmt = $db->prepare($sql);
        $stmt->execute([$todayDate, $todayDate]);
        $planned = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scheduledDate = (new DateTimeImmutable($row['end_date']))
                ->modify('-' . (int)$row['days_before'] . ' days')
                ->format('Y-m-d');

            $insert = $db->prepare(
                'INSERT INTO contract_alerts(contract_id,alert_no,scheduled_date,status,created_at)
                 VALUES(?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE scheduled_date=VALUES(scheduled_date)'
            );
            $insert->execute([
                (int)$row['contract_id'],
                (int)$row['alert_no'],
                $scheduledDate,
                'PENDING',
                now(),
            ]);

            $alertStmt = $db->prepare(
                'SELECT ca.*,c.contract_no,c.end_date,cu.name customer_name,
                        COALESCE(cu.email,(SELECT cc.email FROM customer_contacts cc WHERE cc.customer_id=c.customer_id AND cc.is_active=1 ORDER BY cc.is_primary DESC,cc.id ASC LIMIT 1)) customer_email,
                        uo.email owner_email,ul.email lead_email,us.email sales_email,
                        r.days_before
                 FROM contract_alerts ca
                 JOIN contracts c ON c.id=ca.contract_id
                 JOIN customers cu ON cu.id=c.customer_id
                 JOIN contract_alert_rules r ON r.contract_id=c.id AND r.alert_no=ca.alert_no
                 LEFT JOIN users uo ON uo.id=c.owner_user_id
                 LEFT JOIN users ul ON ul.id=c.lead_user_id
                 LEFT JOIN users us ON us.id=c.sales_user_id
                 WHERE ca.contract_id=? AND ca.alert_no=?
                 LIMIT 1'
            );
            $alertStmt->execute([(int)$row['contract_id'], (int)$row['alert_no']]);
            $alert = $alertStmt->fetch(PDO::FETCH_ASSOC);
            if (!$alert || $alert['sent_at'] !== null) {
                continue;
            }

            if ($alert['attempted_at'] !== null && substr((string)$alert['attempted_at'], 0, 10) === $todayDate) {
                continue;
            }

            $planned[] = $alert;
        }

        return $planned;
    }

    private static function claimAlert(PDO $db, int $alertId, string $todayDate): ?array
    {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT ca.*,c.contract_no,c.end_date,cu.name customer_name,
                        COALESCE(cu.email,(SELECT cc.email FROM customer_contacts cc WHERE cc.customer_id=c.customer_id AND cc.is_active=1 ORDER BY cc.is_primary DESC,cc.id ASC LIMIT 1)) customer_email,
                        uo.email owner_email,ul.email lead_email,us.email sales_email,
                        r.days_before
                 FROM contract_alerts ca
                 JOIN contracts c ON c.id=ca.contract_id
                 JOIN customers cu ON cu.id=c.customer_id
                 JOIN contract_alert_rules r ON r.contract_id=c.id AND r.alert_no=ca.alert_no
                 LEFT JOIN users uo ON uo.id=c.owner_user_id
                 LEFT JOIN users ul ON ul.id=c.lead_user_id
                 LEFT JOIN users us ON us.id=c.sales_user_id
                 WHERE ca.id=?
                 FOR UPDATE'
            );
            $stmt->execute([$alertId]);
            $alert = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$alert || $alert['sent_at'] !== null) {
                $db->commit();
                return null;
            }

            if ($alert['attempted_at'] !== null && substr((string)$alert['attempted_at'], 0, 10) === $todayDate) {
                $db->commit();
                return null;
            }

            $db->prepare('UPDATE contract_alerts SET attempted_at=?,status=? WHERE id=?')
                ->execute([now(), 'PENDING', $alertId]);
            $db->commit();
            return $alert;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function recipients(array $alert): array
    {
        $to = trim((string)($alert['customer_email'] ?? ''));
        $internal = array_values(array_unique(array_filter([
            trim((string)($alert['owner_email'] ?? '')),
            trim((string)($alert['lead_email'] ?? '')),
            trim((string)($alert['sales_email'] ?? '')),
        ])));

        if ($to === '' && $internal !== []) {
            $to = array_shift($internal);
        }

        return [$to, implode(',', $internal)];
    }

    private static function logEmail(PDO $db, int $alertId, string $recipient, string $subject, bool $ok, ?string $error): int
    {
        $stmt = $db->prepare(
            'INSERT INTO email_logs(event_type,entity,entity_id,recipient,subject,status,error_message,created_at)
             VALUES(?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            'CONTRACT_EXPIRY_ALERT',
            'CONTRACT_ALERT',
            $alertId,
            $recipient,
            $subject,
            $ok ? 'SENT' : 'FAILED',
            $error,
            now(),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function run(PDO $db, array $config, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?: new DateTimeImmutable('today');
        $alerts = self::planDueAlerts($db, $today);
        $results = [];

        foreach ($alerts as $candidate) {
            $alert = self::claimAlert($db, (int)$candidate['id'], $today->format('Y-m-d'));
            if ($alert === null) {
                continue;
            }

            $subject = 'CẢNH BÁO HỢP ĐỒNG - Lần ' . (int)$alert['alert_no'] . ' - ' . $alert['contract_no'];
            $body = "Kính gửi Anh/Chị,\n\n"
                . "Hợp đồng {$alert['contract_no']} - {$alert['customer_name']} sẽ hết hạn ngày {$alert['end_date']}.\n"
                . "Đây là cảnh báo lần {$alert['alert_no']} (trước {$alert['days_before']} ngày).\n\n"
                . "Vui lòng kiểm tra kế hoạch gia hạn.\n\nMSP ITSM";

            [$to, $cc] = self::recipients($alert);
            $error = null;
            $ok = false;
            if ($to !== '') {
                $ok = mail_notice($config, $to, $subject, $body, $cc ?: null);
                if (!$ok) {
                    $error = 'mail() failed';
                }
            } else {
                $error = 'No recipient email configured';
            }

            $recipientAudit = $to . ($cc !== '' ? ' | CC: ' . $cc : '');
            $emailLogId = self::logEmail($db, (int)$alert['id'], $recipientAudit, $subject, $ok, $error);
            $status = $ok ? 'SENT' : 'FAILED';
            $sentAt = $ok ? now() : null;

            $update = $db->prepare(
                'UPDATE contract_alerts SET sent_at=?,status=?,recipient=?,cc=?,error_message=?,email_log_id=? WHERE id=?'
            );
            $update->execute([$sentAt, $status, $to, $cc ?: null, $error, $emailLogId, (int)$alert['id']]);

            $results[] = [
                'contract' => $alert['contract_no'],
                'alert' => (int)$alert['alert_no'],
                'status' => $status,
                'scheduled_date' => $alert['scheduled_date'],
            ];
        }

        return $results;
    }
}
