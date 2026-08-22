<?php

declare(strict_types=1);

/**
 * Security regression checks for web entrypoints.
 *
 * These checks intentionally inspect the executable route source so a future
 * refactor cannot silently remove the authentication/authorization boundary.
 * Runtime RBAC semantics remain covered by security_rbac_cross_module_test.php.
 */

function security_route_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('SECURITY ROUTE TEST FAILED: ' . $message);
    }
    echo "PASS: {$message}\n";
}

function route_source(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException("Route file not found: {$path}");
    }
    return (string)file_get_contents($path);
}

$root = dirname(__DIR__);

$auth = route_source($root . '/app/auth.php');
security_route_assert(
    str_contains($auth, 'function require_login(): void') && str_contains($auth, 'current_user()'),
    'Shared login guard exists and checks the current session user'
);
security_route_assert(
    str_contains($auth, 'function require_role(array $roles): void') && str_contains($auth, 'http_response_code(403)'),
    'Shared role guard exists and returns HTTP 403 for unauthorized roles'
);
security_route_assert(
    str_contains($auth, 'u.is_active=1'),
    'Login query rejects inactive users'
);

$protectedRoutes = [
    'public/access.php',
    'public/customer.php',
    'public/contract.php',
    'public/service.php',
    'public/sla.php',
    'public/tasks.php',
    'public/ticket.php',
];

foreach ($protectedRoutes as $relativePath) {
    $source = route_source($root . '/' . $relativePath);
    security_route_assert(
        str_contains($source, 'require_login();') || str_contains($source, 'require_role('),
        "{$relativePath} is behind an authentication/role guard"
    );
}

$customer = route_source($root . '/public/customer.php');
security_route_assert(
    str_contains($customer, 'role_code') && str_contains($customer, "==='CUSTOMER'") && str_contains($customer, '403'),
    'Customer master blocks customer-portal users from internal customer administration'
);

$tasks = route_source($root . '/public/tasks.php');
security_route_assert(
    str_contains($tasks, 'require_login();') && str_contains($tasks, "\$allowedRoles=['ADMIN','IT_LEAD','IT_OWNER','IT_SUPPORT']"),
    'Task management is restricted to approved internal roles'
);
security_route_assert(
    str_contains($tasks, 'verify_csrf();') && str_contains($tasks, "\$_SERVER['REQUEST_METHOD']==='POST'"),
    'Task state-changing requests require POST and CSRF validation'
);
security_route_assert(
    (str_contains($tasks, "['role_code']==='IT_SUPPORT'") || str_contains($tasks, "\$u['role_code']==='IT_SUPPORT'"))
        && str_contains($tasks, 't.assignee_user_id=?'),
    'IT Support task list is restricted to assigned tasks'
);

fwrite(STDOUT, "Security route guard checkpoint PASS\n");
