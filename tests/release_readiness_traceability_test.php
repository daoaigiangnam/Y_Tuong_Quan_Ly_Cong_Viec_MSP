<?php

declare(strict_types=1);

/**
 * Release-readiness traceability test.
 *
 * This is intentionally filesystem-only: it must be runnable without MySQL
 * so that a missing specification/test artifact fails fast in CI.
 */

$root = dirname(__DIR__);

$required = [
    // Core release/readiness documentation.
    'README.md',
    'docs/04_Traceability_Acceptance_Criteria.md',
    'docs/21_Release_Readiness_Audit.md',

    // Module specifications.
    'docs/09_Module_01_Customer_Management.md',
    'docs/10_Module_02_Service_Management.md',
    'docs/11_Module_03_Contract_Management.md',
    'docs/12_Module_04_SLA_Policy_Engine.md',
    'docs/13_Module_05_User_Roles_Permissions.md',
    'docs/14_Module_06_Ticket_Request_Management.md',
    'docs/15_Module_07_Contract_Management.md',
    'docs/16_Module_08_Problem_Management.md',
    'docs/17_Module_09_Change_Management.md',
    'docs/18_Module_09_Task_Management.md',
    'docs/18_Module_10_Knowledge_Management.md',
    'docs/19_Module_11_CMDB_Management.md',

    // Executable module coverage.
    'tests/customer_validation_test.php',
    'tests/service_validation_test.php',
    'tests/contract_validation_test.php',
    'tests/sla_validation_test.php',
    'tests/rbac_validation_test.php',
    'tests/ticket_validation_test.php',
    'tests/contract_alert_mysql_test.php',
    'tests/problem_validation_test.php',
    'tests/change_validation_test.php',
    'tests/task_validation_test.php',
    'tests/task_ui_smoke_test.php',
    'tests/knowledge_validation_test.php',
    'tests/cmdb_validation_test.php',

    // Cross-cutting release gates.
    'tests/release_regression_test.php',
    'tests/security_input_hardening_test.php',
    'tests/security_rbac_cross_module_test.php',
    'tests/security_route_guard_test.php',
    'tests/platform_integration_test.php',
    'tests/platform_integration_negative_test.php',
    'tests/platform_integration_cleanup_test.php',
];

$missing = [];
foreach ($required as $path) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $path)) {
        $missing[] = $path;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "RELEASE TRACEABILITY FAILED: missing artifacts:\n");
    foreach ($missing as $path) {
        fwrite(STDERR, " - {$path}\n");
    }
    exit(1);
}

// Ensure the audit itself documents the historical Task/Module numbering collision.
$audit = file_get_contents($root . '/docs/21_Release_Readiness_Audit.md');
if ($audit === false || strpos($audit, 'Numbering decision') === false) {
    fwrite(STDERR, "RELEASE TRACEABILITY FAILED: numbering decision is not documented.\n");
    exit(1);
}

printf("Release traceability PASS: %d required artifacts verified.\n", count($required));
