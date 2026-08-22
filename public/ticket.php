<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/ticket_policy.php';
require __DIR__ . '/../app/bootstrap.php';
require_login();

if (PHP_SAPI === 'cli') {
    $payload = [
        'customer_id' => 1,
        'service_id' => 1,
        'subject' => 'Network connectivity issue',
        'description' => 'Users cannot access the application.',
        'priority' => 'P2',
    ];
    $errors = validate_ticket_payload($payload);
    echo $errors === [] ? "Ticket validation OK\n" : json_encode($errors, JSON_PRETTY_PRINT) . "\n";
    exit($errors === [] ? 0 : 1);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'module' => 'ticket-request',
    'status' => 'development',
    'lifecycle' => [
        'NEW', 'TRIAGED', 'ASSIGNED', 'IN_PROGRESS',
        'PENDING_CUSTOMER', 'PENDING_VENDOR', 'PENDING_INTERNAL',
        'RESOLVED', 'REOPENED', 'CLOSED'
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
