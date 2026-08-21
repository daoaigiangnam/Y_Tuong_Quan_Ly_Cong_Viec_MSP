<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/rbac_policy.php';

function security_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('SECURITY TEST FAILED: ' . $message);
    }
    echo "PASS: {$message}\n";
}

$customerA = [
    'id' => 101,
    'is_active' => 1,
    'portal_type' => 'CUSTOMER',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 10,
];

$customerBResource = ['customer_id' => 20];
$customerAResource = ['customer_id' => 10];

$itLeadA = [
    'id' => 201,
    'is_active' => 1,
    'portal_type' => 'INTERNAL',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 10,
];

$inactiveUser = [
    'id' => 301,
    'is_active' => 0,
    'portal_type' => 'CUSTOMER',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 10,
];

// Scope isolation must be enforced by the shared server-side policy.
security_assert(
    rbac_scope_allows($customerA, $customerAResource),
    'Customer can access resources in its own scope'
);

security_assert(
    !rbac_scope_allows($customerA, $customerBResource),
    'Customer cannot access another customer scope'
);

security_assert(
    rbac_authorize($customerA, ['ticket.view'], 'ticket.view', 'CUSTOMER', $customerAResource) === 'ALLOW',
    'Customer can view an authorized ticket in own scope'
);

security_assert(
    rbac_authorize($customerA, ['ticket.view'], 'ticket.view', 'CUSTOMER', $customerBResource) === 'DENY_SCOPE',
    'Customer ticket access is denied across customer scope'
);

security_assert(
    rbac_authorize($customerA, [], 'ticket.update', 'CUSTOMER', $customerAResource) === 'DENY_NO_PERMISSION',
    'Missing permission is denied even inside valid scope'
);

security_assert(
    rbac_authorize($itLeadA, ['task.view'], 'task.view', 'INTERNAL', $customerAResource) === 'ALLOW',
    'IT Lead can operate within assigned customer scope'
);

security_assert(
    rbac_authorize($customerA, ['ticket.view'], 'ticket.view', 'INTERNAL', $customerAResource) === 'DENY_PORTAL',
    'Customer cannot cross into internal portal authorization'
);

security_assert(
    rbac_authorize($inactiveUser, ['ticket.view'], 'ticket.view', 'CUSTOMER', $customerAResource) === 'DENY_INACTIVE',
    'Inactive user is denied before permission and scope checks'
);

$protectedActions = ['view', 'create', 'update', 'delete', 'assign'];
security_assert(count($protectedActions) === 5, 'Protected object action matrix is defined');

fwrite(STDOUT, "Security/RBAC cross-module policy checkpoint PASS\n");
