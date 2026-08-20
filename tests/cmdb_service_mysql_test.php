<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/cmdb_policy.php';
require_once __DIR__ . '/../app/services/CmdbService.php';

$dsn = getenv('MSP_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=msp_itsm_test;charset=utf8mb4';
$user = getenv('MSP_TEST_DB_USER') ?: 'root';
$pass = getenv('MSP_TEST_DB_PASSWORD') ?: 'root';
$db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// The migration must seed the standard CI types used by the CMDB module.
$types = (int)$db->query('SELECT COUNT(*) FROM cmdb_ci_types WHERE is_active = 1')->fetchColumn();
if ($types < 8) {
    throw new RuntimeException('CMDB CI type seed is incomplete.');
}

// Seed a deterministic customer fixture. Do not depend on LAST_INSERT_ID()
// after ON DUPLICATE KEY UPDATE; explicitly read the id back by unique code.
$seed = $db->prepare(
    'INSERT INTO customers (code, name, email, status, created_at)
     VALUES (?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        email = VALUES(email),
        status = VALUES(status)'
);
$seed->execute([
    'CMDB-TEST-CUSTOMER',
    'CMDB Integration Test Customer',
    'cmdb-test@example.invalid',
    'ACTIVE',
]);

$customer = $db->prepare('SELECT id FROM customers WHERE code = ? LIMIT 1');
$customer->execute(['CMDB-TEST-CUSTOMER']);
$customerId = (int)$customer->fetchColumn();
if ($customerId <= 0) {
    throw new RuntimeException('CMDB test customer seed failed.');
}

$service = new CmdbService($db);

$ci1 = $service->createCi([
    'customer_id'=>$customerId,
    'ci_type'=>'SERVER',
    'name'=>'CMDB-TEST-APP01',
    'status'=>'PLANNED',
    'criticality'=>'HIGH',
    'customer_visible'=>true,
    'metadata_json'=>['role'=>'application','tier'=>1],
]);
$ci2 = $service->createCi([
    'customer_id'=>$customerId,
    'ci_type'=>'DATABASE',
    'name'=>'CMDB-TEST-DB01',
    'status'=>'ACTIVE',
]);

if ($ci1 <= 0 || $ci2 <= 0 || $ci1 === $ci2) {
    throw new RuntimeException('CI creation failed.');
}

$ci = $db->prepare('SELECT customer_id, ci_type, name, status, criticality, customer_visible, metadata_json FROM cmdb_cis WHERE id=?');
$ci->execute([$ci1]);
$row = $ci->fetch(PDO::FETCH_ASSOC);
if (!$row) throw new RuntimeException('Created CI cannot be read back.');
if ((int)$row['customer_id'] !== $customerId) throw new RuntimeException('CI customer mismatch.');
if ($row['ci_type'] !== 'SERVER' || $row['name'] !== 'CMDB-TEST-APP01') throw new RuntimeException('CI identity fields mismatch.');
if ($row['status'] !== 'PLANNED' || $row['criticality'] !== 'HIGH') throw new RuntimeException('CI initial state mismatch.');
if ((int)$row['customer_visible'] !== 1) throw new RuntimeException('Customer visibility was not persisted.');
$metadata = json_decode((string)$row['metadata_json'], true);
if (!is_array($metadata) || ($metadata['role'] ?? null) !== 'application' || ($metadata['tier'] ?? null) !== 1) {
    throw new RuntimeException('CI metadata was not persisted.');
}

// Transition input is case-insensitive at the service boundary.
$service->transition($ci1, 'active');
$rel = $service->addRelationship($ci1, $ci2, 'USES');

if ($rel <= 0) throw new RuntimeException('Relationship was not created.');

$status = $db->prepare('SELECT status FROM cmdb_cis WHERE id=?');
$status->execute([$ci1]);
if ($status->fetchColumn() !== 'ACTIVE') throw new RuntimeException('CI transition failed.');

$relationship = $db->prepare('SELECT source_ci_id, target_ci_id, relationship_type, status FROM cmdb_ci_relationships WHERE id=?');
$relationship->execute([$rel]);
$relRow = $relationship->fetch(PDO::FETCH_ASSOC);
if (!$relRow) throw new RuntimeException('Relationship cannot be read back.');
if ((int)$relRow['source_ci_id'] !== $ci1 || (int)$relRow['target_ci_id'] !== $ci2 || $relRow['relationship_type'] !== 'USES') {
    throw new RuntimeException('Relationship fields mismatch.');
}

// Database-level uniqueness must reject a duplicate active relationship.
$duplicateRejected = false;
try {
    $service->addRelationship($ci1, $ci2, 'USES');
} catch (PDOException) {
    $duplicateRejected = true;
}
if (!$duplicateRejected) {
    throw new RuntimeException('Duplicate active CI relationship was accepted.');
}

// Database-level foreign keys must reject relationships to non-existent CIs.
$orphanRelationshipRejected = false;
try {
    $service->addRelationship($ci1, 999999999, 'USES');
} catch (PDOException) {
    $orphanRelationshipRejected = true;
}
if (!$orphanRelationshipRejected) {
    throw new RuntimeException('Relationship to a non-existent CI was accepted.');
}

// Database-level foreign keys must also protect CI ownership references.
$orphanCustomerRejected = false;
try {
    $service->createCi([
        'customer_id'=>999999999,
        'ci_type'=>'SERVER',
        'name'=>'CMDB-TEST-ORPHAN-CUSTOMER',
    ]);
} catch (PDOException) {
    $orphanCustomerRejected = true;
}
if (!$orphanCustomerRejected) {
    throw new RuntimeException('CI with a non-existent customer was accepted.');
}

$orphanServiceRejected = false;
try {
    $service->createCi([
        'customer_id'=>$customerId,
        'service_id'=>999999999,
        'ci_type'=>'SERVER',
        'name'=>'CMDB-TEST-ORPHAN-SERVICE',
    ]);
} catch (PDOException) {
    $orphanServiceRejected = true;
}
if (!$orphanServiceRejected) {
    throw new RuntimeException('CI with a non-existent service was accepted.');
}

$orphanOwnerRejected = false;
try {
    $service->createCi([
        'customer_id'=>$customerId,
        'owner_user_id'=>999999999,
        'ci_type'=>'SERVER',
        'name'=>'CMDB-TEST-ORPHAN-OWNER',
    ]);
} catch (PDOException) {
    $orphanOwnerRejected = true;
}
if (!$orphanOwnerRejected) {
    throw new RuntimeException('CI with a non-existent owner was accepted.');
}

$audit = $db->prepare('SELECT action FROM cmdb_ci_audit WHERE ci_id=? ORDER BY id');
$audit->execute([$ci1]);
$actions = $audit->fetchAll(PDO::FETCH_COLUMN);
if (count($actions) < 2 || $actions[0] !== 'CREATE' || !in_array('STATUS_CHANGE', $actions, true)) {
    throw new RuntimeException('Audit records missing or incomplete.');
}

$invalidTransitionRejected = false;
try {
    $service->transition($ci1, 'DISPOSED');
} catch (DomainException) {
    $invalidTransitionRejected = true;
}
if (!$invalidTransitionRejected) {
    throw new RuntimeException('Invalid ACTIVE to DISPOSED transition was accepted.');
}
$status->execute([$ci1]);
if ($status->fetchColumn() !== 'ACTIVE') {
    throw new RuntimeException('Rejected transition changed the CI status.');
}

$invalidRelationshipRejected = false;
try {
    $service->addRelationship($ci1, $ci1, 'USES');
} catch (InvalidArgumentException) {
    $invalidRelationshipRejected = true;
}
if (!$invalidRelationshipRejected) {
    throw new RuntimeException('Self CI relationship was accepted.');
}

// Verify the new FK cascade: removing the target CI removes its relationships.
$db->prepare('DELETE FROM cmdb_cis WHERE id=?')->execute([$ci2]);
$relationshipCount = $db->prepare('SELECT COUNT(*) FROM cmdb_ci_relationships WHERE id=?');
$relationshipCount->execute([$rel]);
if ((int)$relationshipCount->fetchColumn() !== 0) {
    throw new RuntimeException('CI relationship was not removed by FK cascade.');
}

echo "CMDB MySQL integration tests passed\n";
