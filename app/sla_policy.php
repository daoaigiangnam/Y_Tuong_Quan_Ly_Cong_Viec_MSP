<?php

declare(strict_types=1);

/**
 * Pure SLA policy validation.
 *
 * This file intentionally has no database/bootstrap dependency so the SLA
 * validation suite can run as a fast unit test in CI.
 *
 * @return array<string,string>
 */
function validateSlaPolicy(array $data): array
{
    $errors = [];

    $scope = strtoupper(trim((string)($data['scope_type'] ?? '')));
    $allowedScopes = ['GLOBAL', 'SERVICE', 'CONTRACT_SERVICE'];

    if (!in_array($scope, $allowedScopes, true)) {
        $errors['scope_type'] = 'Invalid SLA policy scope.';
    }

    if ($scope === 'SERVICE' && empty($data['service_id'])) {
        $errors['service_id'] = 'Service is required for SERVICE scope.';
    }

    if ($scope === 'CONTRACT_SERVICE') {
        if (empty($data['service_id'])) {
            $errors['service_id'] = 'Service is required for CONTRACT_SERVICE scope.';
        }
        if (empty($data['contract_id'])) {
            $errors['contract_id'] = 'Contract is required for CONTRACT_SERVICE scope.';
        }
    }

    $effectiveFrom = trim((string)($data['effective_from'] ?? ''));
    if ($effectiveFrom !== '' && !slaPolicyValidDate($effectiveFrom)) {
        $errors['effective_from'] = 'Effective From must use YYYY-MM-DD HH:MM:SS.';
    }

    $effectiveTo = trim((string)($data['effective_to'] ?? ''));
    if ($effectiveTo !== '' && !slaPolicyValidDate($effectiveTo)) {
        $errors['effective_to'] = 'Effective To must use YYYY-MM-DD HH:MM:SS.';
    }

    if (
        $effectiveFrom !== '' &&
        $effectiveTo !== '' &&
        slaPolicyValidDate($effectiveFrom) &&
        slaPolicyValidDate($effectiveTo) &&
        strtotime($effectiveTo) <= strtotime($effectiveFrom)
    ) {
        $errors['effective_to'] = 'Effective To must be after Effective From.';
    }

    return $errors;
}

function slaPolicyValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if ($date === false) {
        return false;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    return $date->format('Y-m-d H:i:s') === $value;
}
