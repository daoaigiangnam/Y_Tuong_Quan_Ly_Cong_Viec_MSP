<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/cmdb_policy.php';

function ok(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

ok(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','status'=>'ACTIVE']) === [], 'valid CI');
ok(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'server','name'=>'APP01','criticality'=>'high','environment'=>'prod']) === [], 'case-insensitive CI normalization');
ok(isset(cmdb_validate_ci(['customer_id'=>0,'ci_type'=>'SERVER','name'=>'APP01'])['customer_id']), 'customer required');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'','name'=>'APP01'])['ci_type']), 'type required');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','ci_type_bad'=>'x'])['ci_type']), 'type validation');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'bad type','name'=>'APP01'])['ci_type']), 'type format rejected');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>''])['name']), 'name required');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','status'=>'UNKNOWN'])['status']), 'invalid status rejected');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','criticality'=>'URGENT'])['criticality']), 'invalid criticality rejected');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','environment'=>'LIVE'])['environment']), 'invalid environment rejected');

ok(cmdb_normalize_status(' active ') === 'ACTIVE', 'status normalization');
ok(cmdb_normalize_type(' server ') === 'SERVER', 'type normalization');
ok(cmdb_normalize_criticality(' high ') === 'HIGH', 'criticality normalization');
ok(cmdb_normalize_environment(' prod ') === 'PROD', 'environment normalization');
ok(cmdb_normalize_environment('') === null, 'empty environment normalization');
ok(cmdb_normalize_relationship_type(' runs_on ') === 'RUNS_ON', 'relationship normalization');

ok(cmdb_transition_allowed('planned','active'), 'case-insensitive planned to active');
ok(cmdb_transition_allowed('ACTIVE','maintenance'), 'case-insensitive active to maintenance');
ok(cmdb_transition_allowed('RETIRED','DISPOSED'), 'retired to disposed');
ok(!cmdb_transition_allowed('DISPOSED','ACTIVE'), 'disposed cannot reactivate');

ok(cmdb_relationship_allowed(1,2,'RUNS_ON'), 'valid relationship');
ok(cmdb_relationship_allowed(1,2,' runs_on '), 'relationship normalized');
ok(!cmdb_relationship_allowed(1,1,'RUNS_ON'), 'self relationship rejected');
ok(!cmdb_relationship_allowed(0,2,'RUNS_ON'), 'invalid source rejected');
ok(!cmdb_relationship_allowed(1,2,''), 'relationship type required');
ok(!cmdb_relationship_allowed(1,2,'bad type'), 'invalid relationship format rejected');

echo "CMDB validation tests passed\n";
