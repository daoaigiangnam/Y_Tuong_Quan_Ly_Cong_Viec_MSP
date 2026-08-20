<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/cmdb_policy.php';

function ok(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

ok(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>'APP01','status'=>'ACTIVE']) === [], 'valid CI');
ok(isset(cmdb_validate_ci(['customer_id'=>0,'ci_type'=>'SERVER','name'=>'APP01'])['customer_id']), 'customer required');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'','name'=>'APP01'])['ci_type']), 'type required');
ok(isset(cmdb_validate_ci(['customer_id'=>1,'ci_type'=>'SERVER','name'=>''])['name']), 'name required');
ok(cmdb_transition_allowed('PLANNED','ACTIVE'), 'planned to active');
ok(cmdb_transition_allowed('ACTIVE','MAINTENANCE'), 'active to maintenance');
ok(cmdb_transition_allowed('RETIRED','DISPOSED'), 'retired to disposed');
ok(!cmdb_transition_allowed('DISPOSED','ACTIVE'), 'disposed cannot reactivate');
ok(cmdb_relationship_allowed(1,2,'RUNS_ON'), 'valid relationship');
ok(!cmdb_relationship_allowed(1,1,'RUNS_ON'), 'self relationship rejected');
ok(!cmdb_relationship_allowed(0,2,'RUNS_ON'), 'invalid source rejected');
ok(!cmdb_relationship_allowed(1,2,''), 'relationship type required');

echo "CMDB validation tests passed\n";
