<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/cmdb_policy.php';
require_once __DIR__ . '/../app/services/CmdbService.php';

$dsn = getenv('MSP_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=msp_itsm_test;charset=utf8mb4';
$user = getenv('MSP_TEST_DB_USER') ?: 'root';
$pass = getenv('MSP_TEST_DB_PASSWORD') ?: 'root';
$db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$service = new CmdbService($db);
$customerId = (int)$db->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetchColumn();
if ($customerId <= 0) throw new RuntimeException('No customer seed available.');

$ci1 = $service->createCi([
    'customer_id'=>$customerId,
    'ci_type'=>'SERVER',
    'name'=>'CMDB-TEST-APP01',
    'status'=>'PLANNED',
    'criticality'=>'HIGH',
]);
$ci2 = $service->createCi([
    'customer_id'=>$customerId,
    'ci_type'=>'DATABASE',
    'name'=>'CMDB-TEST-DB01',
    'status'=>'ACTIVE',
]);

$service->transition($ci1, 'ACTIVE');
$rel = $service->addRelationship($ci1, $ci2, 'USES');

if ($rel <= 0) throw new RuntimeException('Relationship was not created.');
$status = $db->prepare('SELECT status FROM cmdb_cis WHERE id=?');
$status->execute([$ci1]);
if ($status->fetchColumn() !== 'ACTIVE') throw new RuntimeException('CI transition failed.');

$audit = $db->prepare('SELECT COUNT(*) FROM cmdb_ci_audit WHERE ci_id=?');
$audit->execute([$ci1]);
if ((int)$audit->fetchColumn() < 2) throw new RuntimeException('Audit records missing.');

echo "CMDB MySQL integration tests passed\n";
