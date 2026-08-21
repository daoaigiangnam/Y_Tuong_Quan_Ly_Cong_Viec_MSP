<?php

declare(strict_types=1);

/**
 * Final release regression gate.
 *
 * This test deliberately checks the assembled platform, not one module in
 * isolation. It verifies that the final migrated schema contains the core
 * cross-module objects and that the most important FK relationships remain
 * intact after every migration has been applied.
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'msp_itsm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$db = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() === 1;
}

$requiredTables = [
    // Foundation
    'roles', 'users', 'customers', 'customer_contacts', 'services',
    // Commercial / ITSM core
    'contracts', 'contract_services', 'contract_alert_rules', 'contract_alerts',
    'tickets', 'ticket_comments', 'ticket_history',
    // Platform governance
    'audit_logs', 'email_logs',
    // Task / CMDB added by later modules
    'tasks', 'task_history',
    'cmdb_ci_types', 'cmdb_cis', 'cmdb_ci_relationships', 'cmdb_ci_audit',
];

foreach ($requiredTables as $table) {
    assertTrue(tableExists($db, $table), "Required release table is missing: {$table}");
}

echo "Required release tables: PASS (" . count($requiredTables) . ")\n";

$requiredColumns = [
    ['customers', 'id'],
    ['customers', 'code'],
    ['contracts', 'customer_id'],
    ['tickets', 'customer_id'],
    ['tickets', 'contract_id'],
    ['tickets', 'service_id'],
    ['tasks', 'ticket_id'],
    ['tasks', 'assignee_user_id'],
    ['task_history', 'task_id'],
    ['cmdb_cis', 'customer_id'],
    ['cmdb_cis', 'service_id'],
    ['cmdb_cis', 'owner_user_id'],
    ['cmdb_ci_relationships', 'source_ci_id'],
    ['cmdb_ci_relationships', 'target_ci_id'],
];

foreach ($requiredColumns as [$table, $column]) {
    assertTrue(
        columnExists($db, $table, $column),
        "Required release column is missing: {$table}.{$column}"
    );
}

echo "Required release columns: PASS (" . count($requiredColumns) . ")\n";

// Verify the key cross-module FK edges survived the complete migration chain.
$fkStmt = $db->query(
    "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND REFERENCED_TABLE_NAME IS NOT NULL"
);

$fks = [];
foreach ($fkStmt->fetchAll() as $row) {
    $fks[$row['TABLE_NAME'] . '.' . $row['COLUMN_NAME']] =
        $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME'];
}

$requiredFks = [
    'contracts.customer_id' => 'customers.id',
    'tickets.customer_id' => 'customers.id',
    'tickets.contract_id' => 'contracts.id',
    'tickets.service_id' => 'services.id',
    'tasks.ticket_id' => 'tickets.id',
    'tasks.assignee_user_id' => 'users.id',
    'task_history.task_id' => 'tasks.id',
    'cmdb_cis.customer_id' => 'customers.id',
    'cmdb_cis.service_id' => 'services.id',
    'cmdb_cis.owner_user_id' => 'users.id',
    'cmdb_ci_relationships.source_ci_id' => 'cmdb_cis.id',
    'cmdb_ci_relationships.target_ci_id' => 'cmdb_cis.id',
];

foreach ($requiredFks as $edge => $target) {
    assertTrue(
        ($fks[$edge] ?? null) === $target,
        "Required cross-module FK is missing or changed: {$edge} -> {$target}"
    );
}

echo "Cross-module foreign keys: PASS (" . count($requiredFks) . ")\n";

// Seed/reference-data sanity checks: these are intentionally read-only.
$roleCount = (int)$db->query('SELECT COUNT(*) FROM roles')->fetchColumn();
$serviceCount = (int)$db->query('SELECT COUNT(*) FROM services')->fetchColumn();
$cmdbTypeCount = (int)$db->query('SELECT COUNT(*) FROM cmdb_ci_types WHERE is_active = 1')->fetchColumn();

assertTrue($roleCount > 0, 'Reference roles are missing.');
assertTrue($serviceCount > 0, 'Reference services are missing.');
assertTrue($cmdbTypeCount >= 8, 'CMDB reference CI types are incomplete.');

echo "Reference data: PASS (roles={$roleCount}, services={$serviceCount}, active_cmdb_types={$cmdbTypeCount})\n";

echo "RELEASE REGRESSION GATE: PASS\n";
