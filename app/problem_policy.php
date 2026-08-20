<?php

declare(strict_types=1);

function problem_types(): array
{
    return ['REACTIVE', 'PROACTIVE'];
}

function problem_priorities(): array
{
    return ['P1', 'P2', 'P3', 'P4'];
}

function problem_statuses(): array
{
    return [
        'NEW',
        'ASSESSING',
        'INVESTIGATING',
        'ROOT_CAUSE_IDENTIFIED',
        'KNOWN_ERROR',
        'FIX_PLANNED',
        'FIX_IMPLEMENTED',
        'VALIDATING',
        'RESOLVED',
        'CLOSED',
        'REJECTED',
        'CANCELLED',
    ];
}

function problem_transition_allowed(string $from, string $to): bool
{
    $map = [
        'NEW' => ['ASSESSING', 'REJECTED', 'CANCELLED'],
        'ASSESSING' => ['INVESTIGATING', 'REJECTED', 'CANCELLED'],
        'INVESTIGATING' => ['ROOT_CAUSE_IDENTIFIED', 'KNOWN_ERROR', 'CANCELLED'],
        'ROOT_CAUSE_IDENTIFIED' => ['KNOWN_ERROR', 'FIX_PLANNED', 'CANCELLED'],
        'KNOWN_ERROR' => ['FIX_PLANNED', 'RESOLVED', 'CANCELLED'],
        'FIX_PLANNED' => ['FIX_IMPLEMENTED', 'CANCELLED'],
        'FIX_IMPLEMENTED' => ['VALIDATING'],
        'VALIDATING' => ['RESOLVED', 'INVESTIGATING'],
        'RESOLVED' => ['CLOSED', 'INVESTIGATING'],
        'CLOSED' => ['INVESTIGATING'],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    return in_array($to, $map[$from] ?? [], true);
}

function validate_problem_payload(array $data): array
{
    $errors = [];
    if (trim((string)($data['title'] ?? '')) === '') {
        $errors[] = 'Title is required.';
    }
    if (trim((string)($data['description'] ?? '')) === '') {
        $errors[] = 'Description is required.';
    }

    $type = strtoupper(trim((string)($data['problem_type'] ?? '')));
    if (!in_array($type, problem_types(), true)) {
        $errors[] = 'Invalid problem type.';
    }

    $priority = strtoupper(trim((string)($data['priority'] ?? 'P3')));
    if (!in_array($priority, problem_priorities(), true)) {
        $errors[] = 'Invalid problem priority.';
    }

    return $errors;
}
