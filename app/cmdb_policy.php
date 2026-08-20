<?php

declare(strict_types=1);

function cmdb_allowed_transitions(string $status): array
{
    return match ($status) {
        'PLANNED' => ['ACTIVE', 'RETIRED'],
        'ACTIVE' => ['MAINTENANCE', 'RETIRED'],
        'MAINTENANCE' => ['ACTIVE', 'RETIRED'],
        'RETIRED' => ['DISPOSED'],
        'DISPOSED' => [],
        default => [],
    };
}

function cmdb_transition_allowed(string $from, string $to): bool
{
    return in_array($to, cmdb_allowed_transitions($from), true);
}

function cmdb_validate_ci(array $data): array
{
    $errors = [];
    if ((int)($data['customer_id'] ?? 0) <= 0) $errors['customer_id'] = 'Customer is required.';
    if (trim((string)($data['ci_type'] ?? '')) === '') $errors['ci_type'] = 'CI type is required.';
    if (trim((string)($data['name'] ?? '')) === '') $errors['name'] = 'CI name is required.';
    if (!in_array(strtoupper((string)($data['status'] ?? 'ACTIVE')), ['PLANNED','ACTIVE','MAINTENANCE','RETIRED','DISPOSED'], true)) {
        $errors['status'] = 'Invalid CI status.';
    }
    return $errors;
}

function cmdb_relationship_allowed(int $sourceId, int $targetId, string $type): bool
{
    return $sourceId > 0 && $targetId > 0 && $sourceId !== $targetId && trim($type) !== '';
}
