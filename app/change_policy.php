<?php

declare(strict_types=1);

function change_types(): array
{
    return ['STANDARD', 'NORMAL', 'EMERGENCY'];
}

function change_priorities(): array
{
    return ['P1', 'P2', 'P3', 'P4'];
}

function change_risks(): array
{
    return ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
}

function change_impacts(): array
{
    return ['LOW', 'MEDIUM', 'HIGH'];
}

function change_statuses(): array
{
    return [
        'DRAFT',
        'ASSESSING',
        'PENDING_APPROVAL',
        'APPROVED',
        'SCHEDULED',
        'IMPLEMENTING',
        'VALIDATING',
        'COMPLETED',
        'FAILED',
        'ROLLED_BACK',
        'CLOSED',
        'REJECTED',
        'CANCELLED',
    ];
}

function change_transition_allowed(string $from, string $to): bool
{
    $map = [
        'DRAFT' => ['ASSESSING', 'CANCELLED'],
        'ASSESSING' => ['PENDING_APPROVAL', 'REJECTED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['REJECTED', 'CANCELLED'],
        'APPROVED' => ['SCHEDULED', 'IMPLEMENTING', 'CANCELLED'],
        'SCHEDULED' => ['IMPLEMENTING', 'CANCELLED'],
        'IMPLEMENTING' => ['VALIDATING', 'FAILED', 'ROLLED_BACK'],
        'VALIDATING' => ['COMPLETED', 'FAILED', 'ROLLED_BACK'],
        'COMPLETED' => ['CLOSED'],
        'FAILED' => ['ROLLED_BACK', 'ASSESSING'],
        'ROLLED_BACK' => ['ASSESSING', 'CLOSED'],
        'CLOSED' => [],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    return in_array($to, $map[$from] ?? [], true);
}

function validate_change_payload(array $data): array
{
    $errors = [];

    if (trim((string)($data['title'] ?? '')) === '') {
        $errors[] = 'Title is required.';
    }
    if (trim((string)($data['description'] ?? '')) === '') {
        $errors[] = 'Description is required.';
    }

    $type = strtoupper(trim((string)($data['change_type'] ?? '')));
    if (!in_array($type, change_types(), true)) {
        $errors[] = 'Invalid change type.';
    }

    $priority = strtoupper(trim((string)($data['priority'] ?? 'P3')));
    if (!in_array($priority, change_priorities(), true)) {
        $errors[] = 'Invalid change priority.';
    }

    $risk = strtoupper(trim((string)($data['risk'] ?? 'MEDIUM')));
    if (!in_array($risk, change_risks(), true)) {
        $errors[] = 'Invalid change risk.';
    }

    $impact = strtoupper(trim((string)($data['impact'] ?? 'MEDIUM')));
    if (!in_array($impact, change_impacts(), true)) {
        $errors[] = 'Invalid change impact.';
    }

    foreach (['implementation_plan', 'rollback_plan', 'success_criteria'] as $field) {
        if (trim((string)($data[$field] ?? '')) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    return $errors;
}
