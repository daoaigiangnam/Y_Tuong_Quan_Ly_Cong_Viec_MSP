<?php

declare(strict_types=1);

final class CmdbService
{
    public function __construct(private PDO $db) {}

    public function createCi(array $data): int
    {
        $errors = cmdb_validate_ci($data);
        if ($errors !== []) throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO cmdb_cis (customer_id, service_id, ci_type, name, code, status, environment, hostname, ip_address, fqdn, manufacturer, model, serial_number, owner_user_id, description, criticality, customer_visible, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                (int)$data['customer_id'], $data['service_id'] ?? null, strtoupper((string)$data['ci_type']),
                trim((string)$data['name']), $data['code'] ?? null, strtoupper((string)($data['status'] ?? 'ACTIVE')),
                $data['environment'] ?? null, $data['hostname'] ?? null, $data['ip_address'] ?? null,
                $data['fqdn'] ?? null, $data['manufacturer'] ?? null, $data['model'] ?? null,
                $data['serial_number'] ?? null, $data['owner_user_id'] ?? null, $data['description'] ?? null,
                strtoupper((string)($data['criticality'] ?? 'MEDIUM')), !empty($data['customer_visible']) ? 1 : 0,
                isset($data['metadata_json']) ? json_encode($data['metadata_json'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $id = (int)$this->db->lastInsertId();
            $audit = $this->db->prepare('INSERT INTO cmdb_ci_audit (ci_id, action, actor_user_id, new_data) VALUES (?, ?, ?, ?)');
            $audit->execute([$id, 'CREATE', $data['actor_user_id'] ?? null, json_encode($data, JSON_UNESCAPED_UNICODE)]);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function transition(int $id, string $to, ?int $actorUserId = null): void
    {
        $to = strtoupper(trim($to));
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status FROM cmdb_cis WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('CI not found.');
            if (!cmdb_transition_allowed((string)$row['status'], $to)) throw new DomainException('CI transition not allowed.');
            $update = $this->db->prepare('UPDATE cmdb_cis SET status = ? WHERE id = ?');
            $update->execute([$to, $id]);
            $audit = $this->db->prepare('INSERT INTO cmdb_ci_audit (ci_id, action, actor_user_id, old_data, new_data) VALUES (?, ?, ?, ?, ?)');
            $audit->execute([$id, 'STATUS_CHANGE', $actorUserId, json_encode(['status'=>$row['status']]), json_encode(['status'=>$to])]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function addRelationship(int $sourceId, int $targetId, string $type, ?int $actorUserId = null): int
    {
        if (!cmdb_relationship_allowed($sourceId, $targetId, $type)) throw new InvalidArgumentException('Invalid CI relationship.');
        $stmt = $this->db->prepare('INSERT INTO cmdb_ci_relationships (source_ci_id, target_ci_id, relationship_type, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$sourceId, $targetId, strtoupper(trim($type)), $actorUserId]);
        return (int)$this->db->lastInsertId();
    }
}
