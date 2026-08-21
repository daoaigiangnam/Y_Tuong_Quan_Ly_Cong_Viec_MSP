<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/TaskService.php';
require_once __DIR__ . '/../app/task_policy.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'msp_itsm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$requiredTables = [
    'roles','users','customers','services','contracts','contract_services',
    'tickets','ticket_comments','ticket_history',
    'problems','problem_tickets',
    'changes','change_history','change_tickets','change_problems',
    'knowledge_articles','knowledge_history','knowledge_links',
    'cmdb_ci_types','cmdb_cis','cmdb_ci_relationships',
    'tasks','task_history','task_policies'
];

foreach ($requiredTables as $table) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException("Required platform table is missing: {$table}");
    }
}

$db->beginTransaction();
try {
    $customerCode = 'INT-CUSTOMER';
    $db->prepare("INSERT INTO customers(code,name,email,status,created_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")
        ->execute([$customerCode, 'Platform Integration Customer', 'integration@example.com', 'ACTIVE']);
    $customerId = (int)$db->lastInsertId();
    if ($customerId === 0) {
        $customerId = (int)$db->query("SELECT id FROM customers WHERE code='INT-CUSTOMER'")->fetchColumn();
    }

    $serviceId = (int)$db->query("SELECT id FROM services WHERE code='IT_SUPPORT' LIMIT 1")->fetchColumn();
    $roleId = (int)$db->query("SELECT id FROM roles WHERE code='IT_OWNER' LIMIT 1")->fetchColumn();
    if ($serviceId === 0 || $roleId === 0) {
        throw new RuntimeException('Reference service or IT_OWNER role is missing.');
    }

    $db->exec("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES('integration-owner','x','Integration Owner','integration-owner@example.com',{$roleId},1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ownerId = (int)$db->lastInsertId();
    if ($ownerId === 0) {
        $ownerId = (int)$db->query("SELECT id FROM users WHERE username='integration-owner'")->fetchColumn();
    }

    $db->exec("INSERT INTO contracts(contract_no,customer_id,contract_type,start_date,end_date,status,owner_user_id,created_at,updated_at) VALUES('INT-CONTRACT',{$customerId},'FULL_PACKAGE',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),'ACTIVE',{$ownerId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $contractId = (int)$db->lastInsertId();
    if ($contractId === 0) {
        $contractId = (int)$db->query("SELECT id FROM contracts WHERE contract_no='INT-CONTRACT'")->fetchColumn();
    }
    $db->prepare('INSERT IGNORE INTO contract_services(contract_id,service_id) VALUES(?,?)')->execute([$contractId, $serviceId]);

    $db->exec("INSERT INTO tickets(ticket_no,customer_id,contract_id,service_id,subject,description,priority,status,owner_user_id,assigned_user_id,created_by_user_id,created_at,updated_at) VALUES('INT-TICKET',{$customerId},{$contractId},{$serviceId},'Platform integration ticket','End-to-end platform integration test','P2','ASSIGNED',{$ownerId},{$ownerId},{$ownerId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ticketId = (int)$db->lastInsertId();
    if ($ticketId === 0) {
        $ticketId = (int)$db->query("SELECT id FROM tickets WHERE ticket_no='INT-TICKET'")->fetchColumn();
    }

    $db->exec("INSERT INTO problems(problem_no,title,description,problem_type,priority,status,customer_id,service_id,owner_user_id,created_by_user_id,created_at,updated_at) VALUES('INT-PROBLEM','Integration Problem','Problem linked to integration ticket','REACTIVE','P2','NEW',{$customerId},{$serviceId},{$ownerId},{$ownerId},NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $problemId = (int)$db->lastInsertId();
    if ($problemId === 0) {
        $problemId = (int)$db->query("SELECT id FROM problems WHERE problem_no='INT-PROBLEM'")->fetchColumn();
    }
    $db->prepare('INSERT IGNORE INTO problem_tickets(problem_id,ticket_id,linked_by_user_id,linked_at) VALUES(?,?,?,NOW())')->execute([$problemId, $ticketId, $ownerId]);

    $db->exec("INSERT INTO cmdb_cis(customer_id,service_id,ci_type,name,code,status,environment,owner_user_id,criticality,customer_visible) VALUES({$customerId},{$serviceId},'SERVER','Integration Server','INT-CI-01','ACTIVE','TEST',{$ownerId},'MEDIUM',0)");
    $ciA = (int)$db->lastInsertId();
    $db->exec("INSERT INTO cmdb_cis(customer_id,service_id,ci_type,name,code,status,environment,owner_user_id,criticality,customer_visible) VALUES({$customerId},{$serviceId},'APPLICATION','Integration Application','INT-CI-02','ACTIVE','TEST',{$ownerId},'MEDIUM',0)");
    $ciB = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO cmdb_ci_relationships(source_ci_id,target_ci_id,relationship_type,created_by) VALUES(?,?,?,?)')
        ->execute([$ciB, $ciA, 'RUNS_ON', $ownerId]);

    $db->prepare("INSERT INTO changes(change_no,title,description,change_type,priority,risk,impact,status,customer_id,service_id,requester_user_id,owner_user_id,approver_user_id,implementation_plan,rollback_plan,test_plan,success_criteria,reason,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([
            'INT-CHANGE', 'Integration Change',
            'Change created by platform end-to-end integration test',
            'NORMAL', 'P2', 'MEDIUM', 'MEDIUM', 'DRAFT',
            $customerId, $serviceId, $ownerId, $ownerId, $ownerId,
            'Apply controlled test change', 'Rollback the controlled test change',
            'Execute automated integration assertions',
            'Change record and relationships persist correctly',
            'Validate Ticket -> Problem -> Change -> CMDB traceability',
            $ownerId
        ]);
    $changeId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO change_tickets(change_id,ticket_id,linked_by_user_id,linked_at) VALUES(?,?,?,NOW())')
        ->execute([$changeId, $ticketId, $ownerId]);
    $db->prepare('INSERT INTO change_problems(change_id,problem_id,linked_by_user_id,linked_at) VALUES(?,?,?,NOW())')
        ->execute([$changeId, $problemId, $ownerId]);
    $db->prepare('INSERT INTO change_history(change_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,NOW())')
        ->execute([$changeId, $ownerId, 'CREATED', 'DRAFT', 'Integration test change created']);

    $db->prepare("INSERT INTO knowledge_articles(article_no,title,slug,summary,body,category,visibility,status,customer_id,service_id,owner_user_id,reviewer_user_id,created_by_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([
            'INT-KB', 'Integration Knowledge Article', 'integration-knowledge-article',
            'Knowledge article created by platform integration test',
            'Documented resolution and change traceability for the integration scenario.',
            'OPERATIONS', 'INTERNAL', 'PUBLISHED',
            $customerId, $serviceId, $ownerId, $ownerId, $ownerId
        ]);
    $knowledgeId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO knowledge_history(article_id,user_id,event,value,note,created_at) VALUES(?,?,?,?,?,NOW())')
        ->execute([$knowledgeId, $ownerId, 'PUBLISHED', 'PUBLISHED', 'Integration test article published']);
    $db->prepare('INSERT INTO knowledge_links(article_id,entity_type,entity_id,linked_by_user_id,linked_at) VALUES(?,?,?,?,NOW())')
        ->execute([$knowledgeId, 'TICKET', $ticketId, $ownerId]);
    $db->prepare('INSERT INTO knowledge_links(article_id,entity_type,entity_id,linked_by_user_id,linked_at) VALUES(?,?,?,?,NOW())')
        ->execute([$knowledgeId, 'CHANGE', $changeId, $ownerId]);

    $policy = TaskPolicy::normalize(['enabled' => true, 'trigger_event' => 'TICKET_ASSIGNMENT']);
    $taskId = TaskService::createForTicketAssignment($db, $ticketId, $ownerId, $ownerId, $policy);
    if ($taskId === null) {
        throw new RuntimeException('Ticket → Task integration failed.');
    }
    TaskService::transition($db, $taskId, 'IN_PROGRESS', $ownerId);
    TaskService::transition($db, $taskId, 'DONE', $ownerId);

    $task = TaskService::get($db, $taskId);
    if (!$task || $task['status'] !== 'DONE' || (int)$task['ticket_id'] !== $ticketId) {
        throw new RuntimeException('Task persistence/lifecycle integration failed.');
    }

    $checks = [
        'contract_customer' => (int)$db->query("SELECT COUNT(*) FROM contracts WHERE id={$contractId} AND customer_id={$customerId}")->fetchColumn(),
        'contract_service' => (int)$db->query("SELECT COUNT(*) FROM contract_services WHERE contract_id={$contractId} AND service_id={$serviceId}")->fetchColumn(),
        'ticket_contract_service' => (int)$db->query("SELECT COUNT(*) FROM tickets WHERE id={$ticketId} AND customer_id={$customerId} AND contract_id={$contractId} AND service_id={$serviceId}")->fetchColumn(),
        'problem_ticket' => (int)$db->query("SELECT COUNT(*) FROM problem_tickets WHERE problem_id={$problemId} AND ticket_id={$ticketId}")->fetchColumn(),
        'change_ticket' => (int)$db->query("SELECT COUNT(*) FROM change_tickets WHERE change_id={$changeId} AND ticket_id={$ticketId}")->fetchColumn(),
        'change_problem' => (int)$db->query("SELECT COUNT(*) FROM change_problems WHERE change_id={$changeId} AND problem_id={$problemId}")->fetchColumn(),
        'change_history' => (int)$db->query("SELECT COUNT(*) FROM change_history WHERE change_id={$changeId}")->fetchColumn(),
        'cmdb_relationship' => (int)$db->query("SELECT COUNT(*) FROM cmdb_ci_relationships WHERE source_ci_id={$ciB} AND target_ci_id={$ciA}")->fetchColumn(),
        'knowledge_ticket_link' => (int)$db->query("SELECT COUNT(*) FROM knowledge_links WHERE article_id={$knowledgeId} AND entity_type='TICKET' AND entity_id={$ticketId}")->fetchColumn(),
        'knowledge_change_link' => (int)$db->query("SELECT COUNT(*) FROM knowledge_links WHERE article_id={$knowledgeId} AND entity_type='CHANGE' AND entity_id={$changeId}")->fetchColumn(),
        'knowledge_history' => (int)$db->query("SELECT COUNT(*) FROM knowledge_history WHERE article_id={$knowledgeId}")->fetchColumn(),
        'task_history' => (int)$db->query("SELECT COUNT(*) FROM task_history WHERE task_id={$taskId}")->fetchColumn(),
    ];

    foreach ($checks as $name => $count) {
        if ($count < 1) {
            throw new RuntimeException("Platform integration assertion failed: {$name}");
        }
    }

    $db->rollBack();
    echo "Platform end-to-end integration smoke test passed: Customer -> Contract -> Service -> Ticket -> Problem -> Change -> CMDB -> Task -> Knowledge\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
