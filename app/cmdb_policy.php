<?php

declare(strict_types=1);

const CMDB_STATUSES = ['PLANNED', 'ACTIVE', 'MAINTENANCE', 'RETIRED', 'DISPOSED'];
const CMDB_CRITICALITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
const CMDB_ENVIRONMENTS = ['DEV', 'TEST', 'UAT', 'STAGING', 'PROD', 'DR'];

function cmdb_normalize_status(string $status): string
{
    return strtoupper(trim($status));
}

function cmdb_normalize_type(string $type): string
{
    return strtoupper(trim($type));
}

function cmdb_normalize_criticality(string $criticality): string
{
    return strtoupper(trim($criticality));
}

function cmdb_normalize_environment(?string $environment): ?string
{
    $value = strtoupper(trim((string)$environment));
    return $value === '' ? null : $value;
}

function cmdb_normalize_relationship_type(string $type): string
{
    return strtoupper(trim($type));
}

function cmdb_allowed_transitions(string $status): array
{
    return match (cmdb_normalize_status($status)) {
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
    $to = cmdb_normalize_status($to);
    return in_array($to, cmdb_allowed_transitions($from), true);
}

function cmdb_validate_ci(array $data): array
{
    $errors = [];
    $customerId = (int)($data['customer_id'] ?? 0);
    $type = cmdb_normalize_type((string)($data['ci_type'] ?? ''));
    $name = trim((string)($data['name'] ?? ''));
    $status = cmdb_normalize_status((string)($data['status'] ?? 'ACTIVE'));
    $criticality = cmdb_normalize_criticality((string)($data['criticality'] ?? 'MEDIUM'));
    $environment = cmdb_normalize_environment($data['environment'] ?? null);

    if ($customerId <= 0) $errors['customer_id'] = 'Customer is required.';
    if ($type === '') {
        $errors['ci_type'] = 'CI type is required.';
    } elseif (!preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $type)) {
        $errors['ci_type'] = 'Invalid CI type format.';
    }
    if ($name === '') {
        $errors['name'] = 'CI name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors['name'] = 'CI name must not exceed 255 characters.';
    }
    if (!in_array($status, CMDB_STATUSES, true)) {
        $errors['status'] = 'Invalid CI status.';
    }
    if (!in_array($criticality, CMDB_CRITICALITIES, true)) {
        $errors['criticality'] = 'Invalid CI criticality.';
    }
    if ($environment !== null && !in_array($environment, CMDB_ENVIRONMENTS, true)) {
        $errors['environment'] = 'Invalid CI environment.';
    }

    return $errors;
}

function cmdb_relationship_allowed(int $sourceId, int $targetId, string $type): bool
{
    $normalizedType = cmdb_normalize_relationship_type($type);
    return $sourceId > 0
        && $targetId > 0
        && $sourceId !== $targetId
        && preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $normalizedType) === 1;
}
