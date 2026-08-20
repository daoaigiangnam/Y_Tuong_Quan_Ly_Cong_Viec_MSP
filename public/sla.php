<?php

declare(strict_types=1);

/**
 * Module 04 — SLA Policy Management
 * Pure validation/demo endpoint for the current development stage.
 *
 * This file intentionally has no database/bootstrap dependency so that
 * validation tests can run in GitHub Actions without external infrastructure.
 */

function validateSlaPolicy(array $data): array
{
    $errors = [];
    $code = trim((string)($data['policy_code'] ?? ''));
    $name = trim((string)($data['policy_name'] ?? ''));
    $scope = strtoupper(trim((string)($data['scope_type'] ?? '')));
    $effectiveFrom = trim((string)($data['effective_from'] ?? ''));
    $effectiveTo = trim((string)($data['effective_to'] ?? ''));

    if ($code === '' || strlen($code) < 2 || strlen($code) > 50) {
        $errors['policy_code'] = 'Policy code is required and must be 2-50 characters.';
    }
    if ($name === '' || strlen($name) < 2 || strlen($name) > 200) {
        $errors['policy_name'] = 'Policy name is required and must be 2-200 characters.';
    }

    $allowedScopes = ['GLOBAL', 'SERVICE', 'CONTRACT_SERVICE'];
    if (!in_array($scope, $allowedScopes, true)) {
        $errors['scope_type'] = 'Invalid SLA policy scope.';
    }

    if ($scope === 'SERVICE' && (int)($data['service_id'] ?? 0) <= 0) {
        $errors['service_id'] = 'Service is required for SERVICE scope.';
    }

    if ($scope === 'CONTRACT_SERVICE') {
        if ((int)($data['contract_id'] ?? 0) <= 0) {
            $errors['contract_id'] = 'Contract is required for CONTRACT_SERVICE scope.';
        }
        if ((int)($data['service_id'] ?? 0) <= 0) {
            $errors['service_id'] = 'Service is required for CONTRACT_SERVICE scope.';
        }
    }

    $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $effectiveFrom);
    if (!$from || $from->format('Y-m-d H:i:s') !== $effectiveFrom) {
        $errors['effective_from'] = 'Effective From must use YYYY-MM-DD HH:MM:SS.';
    }

    if ($effectiveTo !== '') {
        $to = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $effectiveTo);
        if (!$to || $to->format('Y-m-d H:i:s') !== $effectiveTo) {
            $errors['effective_to'] = 'Effective To must use YYYY-MM-DD HH:MM:SS.';
        } elseif ($from && $to <= $from) {
            $errors['effective_to'] = 'Effective To must be after Effective From.';
        }
    }

    return $errors;
}

if (PHP_SAPI === 'cli') {
    $sample = [
        'policy_code' => 'SLA-NET-001',
        'policy_name' => 'Network Standard SLA',
        'scope_type' => 'SERVICE',
        'service_id' => 1,
        'effective_from' => '2026-01-01 00:00:00',
    ];

    $errors = validateSlaPolicy($sample);
    echo $errors === [] ? "SLA validation OK\n" : json_encode($errors, JSON_PRETTY_PRINT) . "\n";
    exit($errors === [] ? 0 : 1);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'module' => 'sla-policy',
    'status' => 'development',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
