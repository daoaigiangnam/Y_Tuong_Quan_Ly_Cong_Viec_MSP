<?php

declare(strict_types=1);

/**
 * Module 04 — SLA Policy Management.
 *
 * The route is protected by the shared application authentication guard.
 * Validation rules live in app/sla_policy.php so CLI tests do not need a DB.
 */

require_once __DIR__ . '/../app/sla_policy.php';
require __DIR__ . '/../app/bootstrap.php';
require_login();

$u = current_user();
if (($u['role_code'] ?? '') === 'CUSTOMER') {
    http_response_code(403);
    exit('Forbidden');
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'module' => 'sla-policy',
    'status' => 'development',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
