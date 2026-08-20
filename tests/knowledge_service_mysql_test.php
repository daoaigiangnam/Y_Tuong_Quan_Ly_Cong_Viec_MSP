<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/knowledge_policy.php';
require_once __DIR__ . '/../app/services/KnowledgeService.php';

$pdo = new PDO(
    'mysql:host=' . ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306') . ';dbname=' . ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'msp_itsm') . ';charset=utf8mb4',
    $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
    $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pdo->exec("INSERT INTO users (username, password_hash, full_name, email, is_active, created_at, updated_at) VALUES ('kb_test','x','KB Test','kb-test@example.test',1,NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
$userId = (int)$pdo->lastInsertId();
if ($userId < 1) { $userId = (int)$pdo->query("SELECT id FROM users WHERE username='kb_test'")->fetchColumn(); }

$id = KnowledgeService::create($pdo, [
    'title'=>'VPN troubleshooting guide',
    'body'=>'Check tunnel, credentials and DNS.',
    'category'=>'Network',
    'visibility'=>'INTERNAL'
], $userId);

KnowledgeService::transition($pdo, $id, 'IN_REVIEW', $userId);
KnowledgeService::transition($pdo, $id, 'PUBLISHED', $userId);
KnowledgeService::link($pdo, $id, 'TICKET', 1, $userId);

$row = $pdo->prepare('SELECT status,version FROM knowledge_articles WHERE id=?');
$row->execute([$id]);
$article = $row->fetch();
if (!$article || $article['status'] !== 'PUBLISHED') throw new RuntimeException('Knowledge article was not published.');

$history = $pdo->prepare('SELECT COUNT(*) FROM knowledge_history WHERE article_id=?');
$history->execute([$id]);
if ((int)$history->fetchColumn() < 3) throw new RuntimeException('Knowledge history incomplete.');

echo "Knowledge MySQL integration tests passed\n";
