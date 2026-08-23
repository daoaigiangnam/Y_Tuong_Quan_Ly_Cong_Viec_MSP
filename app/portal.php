<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Portal routing helpers. Authorization remains server-side through require_permission().
 */
function portal_home(string $portal): string
{
    return strtoupper($portal) === 'CUSTOMER' ? '?page=customer_portal' : '?page=service_portal';
}

function require_portal(string $portal): void
{
    require_login();
    $user = current_user();
    if (strtoupper((string)($user['portal_type'] ?? '')) !== strtoupper($portal)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function portal_label(string $portal): string
{
    return strtoupper($portal) === 'CUSTOMER' ? 'Customer Portal' : 'Service Portal';
}
