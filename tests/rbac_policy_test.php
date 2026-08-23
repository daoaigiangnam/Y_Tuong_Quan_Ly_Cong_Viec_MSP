<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/rbac_policy.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' | expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$serviceUser = [
    'id' => 10,
    'is_active' => 1,
    'portal_type' => 'SERVICE',
    'scope_type' => 'GLOBAL',
];

$customerUser = [
    'id' => 20,
    'is_active' => 1,
    'portal_type' => 'CUSTOMER',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 7,
];

$assignedUser = [
    'id' => 30,
    'is_active' => 1,
    'portal_type' => 'SERVICE',
    'scope_type' => 'ASSIGNED',
];

assert_same(true, rbac_portal_allowed($serviceUser, 'SERVICE'), 'service portal should be allowed');
assert_same(false, rbac_portal_allowed($serviceUser, 'CUSTOMER'), 'service portal should reject customer portal');
assert_same(true, rbac_scope_allows($customerUser, ['customer_id' => 7]), 'customer scope should allow own customer');
assert_same(false, rbac_scope_allows($customerUser, ['customer_id' => 8]), 'customer scope should reject another customer');
assert_same(true, rbac_scope_allows($assignedUser, ['assigned_user_id' => 30]), 'assigned scope should allow assigned record');
assert_same(false, rbac_scope_allows($assignedUser, ['assigned_user_id' => 31]), 'assigned scope should reject another assignee');
assert_same('ALLOW', rbac_authorize($serviceUser, ['ticket.view'], 'ticket.view', 'SERVICE', ['customer_id' => 7]));
assert_same('DENY_NO_PERMISSION', rbac_authorize($serviceUser, [], 'ticket.view', 'SERVICE'));
assert_same('DENY_PORTAL', rbac_authorize($customerUser, ['ticket.view'], 'ticket.view', 'SERVICE'));
assert_same('DENY_SCOPE', rbac_authorize($customerUser, ['ticket.view'], 'ticket.view', 'CUSTOMER', ['customer_id' => 8]));
assert_same('DENY_INACTIVE', rbac_authorize(['is_active' => 0], ['ticket.view'], 'ticket.view', 'SERVICE'));

echo "RBAC policy tests: PASS\n";
