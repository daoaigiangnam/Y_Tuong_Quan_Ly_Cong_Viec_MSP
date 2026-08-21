<?php

declare(strict_types=1);

/**
 * UAT readiness gate.
 *
 * This is intentionally self-contained: it verifies that the release branch
 * contains the business/technical acceptance artifacts required before a
 * real-user UAT cycle. It does not replace browser or stakeholder testing.
 */

function uat_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('UAT READINESS FAILED: ' . $message);
    }

    echo "PASS: {$message}\n";
}

function file_required(string $root, string $relativePath): string
{
    $path = $root . '/' . $relativePath;
    uat_assert(is_file($path), "Required artifact exists: {$relativePath}");
    return (string) file_get_contents($path);
}

$root = dirname(__DIR__);

// Business and architecture acceptance baseline.
file_required($root, 'docs/01_BOD_Business_Case.md');
file_required($root, 'docs/02_DEV_UIUX_Specification.md');
file_required($root, 'docs/03_Data_Model_Technical_Blueprint.md');
file_required($root, 'docs/04_Traceability_Acceptance_Criteria.md');
file_required($root, 'docs/22_Deployment_Hardening.md');

$moduleDocs = [
    '09_Module_01_Customer_Management.md',
    '10_Module_02_Service_Management.md',
    '11_Module_03_Contract_Management.md',
    '12_Module_04_SLA_Policy_Engine.md',
    '13_Module_05_User_Roles_Permissions.md',
    '14_Module_06_Ticket_Request_Management.md',
    '15_Module_07_Contract_Management.md',
    '16_Module_08_Problem_Management.md',
    '17_Module_09_Change_Management.md',
    '18_Module_10_Knowledge_Management.md',
    '19_Module_11_CMDB_Management.md',
    '18_Module_09_Task_Management.md',
];

foreach ($moduleDocs as $doc) {
    file_required($root, 'docs/' . $doc);
}

// Core runtime entrypoints must remain present for UAT execution.
foreach ([
    'public/access.php',
    'public/customer.php',
    'public/service.php',
    'public/contract.php',
    'public/sla.php',
    'public/ticket.php',
    'public/tasks.php',
] as $route) {
    file_required($root, $route);
}

// Core policy/service layer must remain available.
foreach ([
    'app/auth.php',
    'app/task_policy.php',
    'app/services/TicketService.php',
    'app/services/TaskService.php',
    'app/services/ContractService.php',
    'app/services/ProblemService.php',
    'app/services/ChangeService.php',
    'app/services/KnowledgeService.php',
    'app/services/CmdbService.php',
] as $runtimeFile) {
    file_required($root, $runtimeFile);
}

// Acceptance criteria must explicitly define evidence and expected outcomes.
$acceptance = file_required($root, 'docs/04_Traceability_Acceptance_Criteria.md');
uat_assert(
    stripos($acceptance, 'acceptance') !== false &&
    stripos($acceptance, 'evidence') !== false,
    'Traceability document defines acceptance and evidence expectations'
);

// UAT must have a clear business-owner sign-off boundary.
$bod = file_required($root, 'docs/01_BOD_Business_Case.md');
uat_assert(
    stripos($bod, 'business') !== false &&
    (stripos($bod, 'owner') !== false || stripos($bod, 'approval') !== false),
    'Business case contains ownership/approval language'
);

fwrite(STDOUT, "UAT readiness checkpoint PASS\n");
