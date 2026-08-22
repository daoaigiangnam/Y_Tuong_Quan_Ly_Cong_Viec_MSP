<?php

declare(strict_types=1);

/**
 * Module 07 — Contract business rules independent from persistence/UI.
 *
 * The procedural functions are kept as the canonical policy API. The
 * ContractPolicy facade below provides the service-layer API used by
 * ContractService while remaining backward-compatible with existing callers.
 */

function contract_allowed_types(): array
{
    return ['FULL_PACKAGE', 'PAY_PER_INCIDENT'];
}

function contract_allowed_transitions(string $status): array
{
    return match (strtoupper($status)) {
        'DRAFT' => ['PENDING_SIGN', 'CANCELLED'],
        'PENDING_SIGN' => ['ACTIVE', 'CANCELLED'],
        'ACTIVE' => ['EXPIRING', 'EXPIRED', 'RENEWED', 'CANCELLED'],
        'EXPIRING' => ['ACTIVE', 'EXPIRED', 'RENEWED', 'CANCELLED'],
        'EXPIRED' => ['RENEWED'],
        'RENEWED' => [],
        'CANCELLED' => [],
        default => [],
    };
}

function contract_transition_allowed(string $from, string $to): bool
{
    return in_array(
        strtoupper($to),
        contract_allowed_transitions(strtoupper($from)),
        true
    );
}

/**
 * Service-layer policy facade.
 *
 * Throws InvalidArgumentException when a lifecycle transition is not allowed.
 */
final class ContractPolicy
{
    public static function assertTransition(string $from, string $to): void
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Contract status transition requires both source and target status.');
        }

        if (!contract_transition_allowed($from, $to)) {
            throw new InvalidArgumentException(
                sprintf('Invalid contract status transition: %s -> %s.', $from, $to)
            );
        }
    }
}

function validate_contract_payload(array $data): array
{
    $errors = [];
    $customerId = (int)($data['customer_id'] ?? 0);
    $type = strtoupper(trim((string)($data['contract_type'] ?? '')));
    $start = trim((string)($data['start_date'] ?? ''));
    $end = trim((string)($data['end_date'] ?? ''));

    if ($customerId <= 0) {
        $errors['customer_id'] = 'Customer is required.';
    }
    if (!in_array($type, contract_allowed_types(), true)) {
        $errors['contract_type'] = 'Unsupported contract type.';
    }

    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $start);
    $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $end);

    if (!$startDate || $startDate->format('Y-m-d') !== $start) {
        $errors['start_date'] = 'Invalid start date.';
    }
    if (!$endDate || $endDate->format('Y-m-d') !== $end) {
        $errors['end_date'] = 'Invalid end date.';
    }
    if ($startDate && $endDate && $endDate < $startDate) {
        $errors['date_range'] = 'End date cannot be before start date.';
    }

    return $errors;
}

function contract_alert_date(string $endDate, int $daysBefore): string
{
    if ($daysBefore < 0) {
        throw new InvalidArgumentException('days_before must be non-negative.');
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
    if (!$date || $date->format('Y-m-d') !== $endDate) {
        throw new InvalidArgumentException('Invalid contract end date.');
    }

    return $date->modify('-' . $daysBefore . ' days')->format('Y-m-d');
}

function default_contract_alert_rules(): array
{
    return [
        ['alert_no' => 1, 'days_before' => 90],
        ['alert_no' => 2, 'days_before' => 60],
        ['alert_no' => 3, 'days_before' => 30],
    ];
}
