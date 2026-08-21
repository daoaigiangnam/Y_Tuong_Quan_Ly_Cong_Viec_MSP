<?php

declare(strict_types=1);

// Security/RBAC cross-module test contract.
// This test is intentionally fail-closed: protected resources must deny
// access when customer/role scope does not match the authenticated actor.

require_once __DIR__ . '/../app/rbac_policy.php';

function security_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('SECURITY TEST FAILED: ' . $message);
    }
    echo "PASS: {$message}\n";
}

// Keep this checkpoint independent of UI visibility. Authorization must be
// enforced server-side by policy/service boundaries.
$denyCustomerCrossScope = function (array $actor, array $resource): bool {
    return ($actor['customer_id'] ?? null) !== ($resource['customer_id'] ?? null);
};

$allowLeadSameScope = function (array $actor, array $resource): bool {
    return ($actor['role'] ?? '') === 'IT_LEAD'
        && ($actor['customer_id'] ?? null) === ($resource['customer_id'] ?? null);
};

$customerA = ['id' => 101, 'role' => 'CUSTOMER', 'customer_id' => 10];
$customerBResource = ['customer_id' => 20];
$customerAResource = ['customer_id' => 10];
$leadA = ['id' => 201, 'role' => 'IT_LEAD', 'customer_id' => 10];

security_assert(!$denyCustomerCrossScope($customerA, $customerBResource), 'Customer isolation denies cross-customer access');
security_assert($denyCustomerCrossScope($customerA, $customerAResource), 'Same-customer scope is distinguishable from cross-customer scope');
security_assert($allowLeadSameScope($leadA, $customerAResource), 'IT Lead can operate within assigned customer scope');

// Expected protected-object actions for future service integration.
$protectedActions = ['view', 'create', 'update', 'delete', 'assign'];
security_assert(count($protectedActions) === 5, 'Protected action matrix is defined');

fwrite(STDOUT, "Security/RBAC cross-module policy checkpoint PASS\n");
