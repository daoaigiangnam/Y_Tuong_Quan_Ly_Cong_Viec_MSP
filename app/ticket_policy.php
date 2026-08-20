<?php

declare(strict_types=1);

/**
 * Module 06 — Ticket business rules kept independent from persistence/UI.
 */

function ticket_allowed_transitions(string $status): array
{
    return match ($status) {
        'NEW' => ['TRIAGED', 'CLOSED'],
        'TRIAGED' => ['ASSIGNED', 'IN_PROGRESS', 'CLOSED'],
        'ASSIGNED' => ['IN_PROGRESS', 'PENDING_INTERNAL', 'PENDING_VENDOR', 'PENDING_CUSTOMER'],
        'IN_PROGRESS' => ['PENDING_CUSTOMER', 'PENDING_VENDOR', 'PENDING_INTERNAL', 'RESOLVED'],
        'PENDING_CUSTOMER', 'PENDING_VENDOR', 'PENDING_INTERNAL' => ['IN_PROGRESS', 'RESOLVED'],
        'RESOLVED' => ['CLOSED', 'REOPENED'],
        'REOPENED' => ['IN_PROGRESS', 'PENDING_CUSTOMER', 'PENDING_VENDOR', 'PENDING_INTERNAL', 'RESOLVED'],
        'CLOSED' => [],
        default => [],
    };
}

function ticket_transition_allowed(string $from, string $to): bool
{
    return in_array($to, ticket_allowed_transitions($from), true);
}

function validate_ticket_payload(array $data): array
{
    $errors = [];
    $customerId = (int)($data['customer_id'] ?? 0);
    $serviceId = (int)($data['service_id'] ?? 0);
    $subject = trim((string)($data['subject'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $priority = strtoupper(trim((string)($data['priority'] ?? '')));

    if ($customerId <= 0) {
        $errors['customer_id'] = 'Customer is required.';
    }
    if ($serviceId <= 0) {
        $errors['service_id'] = 'Service is required.';
    }
    if (strlen($subject) < 5 || strlen($subject) > 255) {
        $errors['subject'] = 'Subject must be 5-255 characters.';
    }
    if ($description === '') {
        $errors['description'] = 'Description is required.';
    }
    if (!in_array($priority, ['P1', 'P2', 'P3', 'P4'], true)) {
        $errors['priority'] = 'Invalid priority.';
    }

    return $errors;
}

function can_view_ticket_note(string $portal, string $visibility): bool
{
    if ($visibility === 'INTERNAL_ONLY') {
        return $portal === 'INTERNAL';
    }
    return in_array($portal, ['INTERNAL', 'CUSTOMER'], true);
}

function can_reopen_ticket(string $status, string $reason): bool
{
    return $status === 'RESOLVED' && trim($reason) !== '';
}
