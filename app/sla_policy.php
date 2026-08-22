<?php

declare(strict_types=1);

/**
 * SLA policy validation rules shared by the web route and standalone tests.
 *
 * This file intentionally has no database/session dependency so validation
 * tests can run in isolation.
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
