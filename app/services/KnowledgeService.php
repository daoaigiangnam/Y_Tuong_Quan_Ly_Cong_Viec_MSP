<?php

declare(strict_types=1);

require_once __DIR__ . '/../knowledge_policy.php';

final class KnowledgeService
{
    private static function unit(PDO $db): ?string
    {
        if (!$db->inTransaction()) { $db->beginTransaction(); return null; }
        $sp = 'knowledge_sp_' . bin2hex(random_bytes(4));
        $db->exec('SAVEPOINT ' . $sp);
        return $sp;
    }

    private static function commit(PDO $db, ?string $sp): void
    {
        if ($sp === null) { $db->commit(); return; }
        $db->exec('RELEASE SAVEPOINT ' . $sp);
    }

    private static function rollback(PDO $db, ?string $sp): void
    {
        if ($sp === null) { if ($db->inTransaction()) $db->rollBack(); return; }
        if ($db->inTransaction()) { $db->exec('ROLLBACK TO SAVEPOINT ' . $sp); $db->exec('RELEASE SAVEPOINT ' . $sp); }
    }

    public static function create(PDO $db, array $data, int $userId): int
    {
        $errors = validate_knowledge_payload($data);
        if ($errors !== [] || $userId < 1) throw new InvalidArgumentException('Invalid knowledge article: ' . implode(' ', $errors));
        $no = trim((string)($data['article_no'] ?? ''));
        if ($no === '') $no = 'KB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $slug = trim((string)($data['slug'] ?? ''));
        if ($slug === '') $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$data['title']), '-'));
        $now = date('Y-m-d H:i:s');
        $sp = self::unit($db);
        try {
            $stmt = $db->prepare('INSERT INTO knowledge_articles(article_no,title,slug,summary,body,category,visibility,status,customer_id,service_id,owner_user_id,reviewer_user_id,version,expires_at,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $no, trim((string)$data['title']), $slug,
                !empty($data['summary']) ? trim((string)$data['summary']) : null,
                trim((string)$data['body']), trim((string)$data['category']),
                strtoupper((string)($data['visibility'] ?? 'INTERNAL')), 'DRAFT',
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                !empty($data['service_id']) ? (int)$data['service_id'] : null,
                !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : $userId,
                !empty($data['reviewer_user_id']) ? (int)$data['reviewer_user_id'] : null,
                1, $data['expires_at'] ?? null, $userId, $now, $now
            ]);
            $id = (int)$db->lastInsertId();
            self::history($db, $id, $userId, 'CREATED', 'DRAFT', null);
            self::commit($db, $sp); return $id;
        } catch (Throwable $e) { self::rollback($db, $sp); throw $e; }
    }

    public static function transition(PDO $db, int $id, string $to, int $userId): void
    {
        $to = strtoupper(trim($to));
        if ($id < 1 || $userId < 1 || !in_array($to, knowledge_statuses(), true)) throw new InvalidArgumentException('Invalid knowledge transition request.');
        $sp = self::unit($db);
        try {
            $q = $db->prepare('SELECT status FROM knowledge_articles WHERE id=? FOR UPDATE'); $q->execute([$id]); $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Knowledge article not found.');
            $from = strtoupper((string)$row['status']);
            if (!knowledge_transition_allowed($from, $to)) throw new RuntimeException("Invalid knowledge transition {$from} -> {$to}");
            $now = date('Y-m-d H:i:s');
            $published = $to === 'PUBLISHED' ? $now : null;
            $update = $db->prepare('UPDATE knowledge_articles SET status=?,published_at=COALESCE(?,published_at),updated_at=? WHERE id=?');
            $update->execute([$to, $published, $now, $id]);
            self::history($db, $id, $userId, 'STATUS_CHANGED', $to, "{$from} -> {$to}");
            self::commit($db, $sp);
        } catch (Throwable $e) { self::rollback($db, $sp); throw $e; }
    }

    public static function updateContent(PDO $db, int $id, array $data, int $userId): void
    {
        if ($id < 1 || $userId < 1) throw new InvalidArgumentException('Article and user are required.');
        $allowed = ['title','slug','summary','body','category','visibility','expires_at']; $sets=[]; $values=[];
        foreach ($allowed as $field) if (array_key_exists($field,$data)) { $sets[]=$field.'=?'; $values[]=$data[$field] !== null ? trim((string)$data[$field]) : null; }
        if ($sets === []) throw new InvalidArgumentException('No knowledge fields supplied.');
        if (isset($data['visibility']) && !in_array(strtoupper((string)$data['visibility']), ['INTERNAL','CUSTOMER','PUBLIC'], true)) throw new InvalidArgumentException('Invalid visibility.');
        $values[] = date('Y-m-d H:i:s'); $values[]=$id;
        $stmt=$db->prepare('UPDATE knowledge_articles SET '.implode(',',$sets).',version=version+1,updated_at=? WHERE id=?'); $stmt->execute($values);
        if ($stmt->rowCount()<1) throw new RuntimeException('Article not found or unchanged.');
        self::history($db,$id,$userId,'CONTENT_UPDATED',null,implode(',',array_keys(array_intersect_key($data,array_flip($allowed)))));
    }

    public static function link(PDO $db, int $id, string $entityType, int $entityId, int $userId): void
    {
        if ($id<1 || $entityId<1 || $userId<1 || !in_array(strtoupper($entityType),['TICKET','PROBLEM','CHANGE'],true)) throw new InvalidArgumentException('Invalid knowledge link.');
        $type=strtoupper($entityType); $stmt=$db->prepare('INSERT IGNORE INTO knowledge_links(article_id,entity_type,entity_id,linked_by_user_id,linked_at) VALUES(?,?,?,?,?)');
        $stmt->execute([$id,$type,$entityId,$userId,date('Y-m-d H:i:s')]);
        if ($stmt->rowCount()>0) self::history($db,$id,$userId,'LINKED',$type.':'.$entityId,null);
    }

    private static function history(PDO $db,int $id,int $userId,string $event,?string $value,?string $note): void
    {
        $stmt=$db->prepare('INSERT INTO knowledge_history(article_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$id,$userId,$event,$value,$note,date('Y-m-d H:i:s')]);
    }
}
