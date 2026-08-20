<?php

declare(strict_types=1);

require __DIR__ . '/../app/rbac_policy.php';

function assert_same_value(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message} | expected={$expected} actual={$actual}\n");
        exit(1);
    }
}

$internal = [
    'id' => 10,
    'is_active' => 1,
    'portal_type' => 'INTERNAL',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 100,
];

$customer = [
    'id' => 20,
    'is_active' => 1,
    'portal_type' => 'CUSTOMER',
    'scope_type' => 'CUSTOMER',
    'customer_id' => 100,
];

assert_same_value(true, rbac_is_active($internal), 'active user');
assert_same_value(false, rbac_is_active(['is_active' => 0]), 'inactive user');
assert_same_value(true, rbac_has_permission(['ticket.view', 'ticket.update'], 'ticket.view'), 'permission exists');
assert_same_value(false, rbac_has_permission(['ticket.view'], 'ticket.update'), 'permission missing');
assert_same_value(true, rbac_portal_allowed($customer, 'CUSTOMER'), 'customer portal allowed');
assert_same_value(false, rbac_portal_allowed($customer, 'INTERNAL'), 'customer portal isolation');
assert_same_value(true, rbac_scope_allows($customer, ['customer_id' => 100]), 'customer scope match');
assert_same_value(false, rbac_scope_allows($customer, ['customer_id' => 200]), 'customer scope isolation');
assert_same_value('ALLOW', rbac_authorize($internal, ['customer.view'], 'customer.view', 'INTERNAL', ['customer_id' => 100]), 'authorized internal access');
assert_same_value('DENY_NO_PERMISSION', rbac_authorize($internal, [], 'customer.view', 'INTERNAL', ['customer_id' => 100]), 'permission denial');
assert_same_value('DENY_SCOPE', rbac_authorize($internal, ['customer.view'], 'customer.view', 'INTERNAL', ['customer_id' => 200]), 'scope denial');
assert_same_value('DENY_PORTAL', rbac_authorize($customer, ['customer.view'], 'customer.view', 'INTERNAL', ['customer_id' => 100]), 'portal denial');
assert_same_value('DENY_INACTIVE', rbac_authorize(['is_active' => 0, 'portal_type' => 'INTERNAL'], ['customer.view'], 'customer.view', 'INTERNAL'), 'inactive denial');

$global = [
    'id' => 1,
    'is_active' => 1,
    'portal_type' => 'INTERNAL',
    'scope_type' => 'GLOBAL',
];
assert_same_value(true, rbac_scope_allows($global, ['customer_id' => 999, 'service_id' => 999]), 'global scope');

echo "RBAC validation tests: PASS\n";
