<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/change_policy.php';

$valid = [
    'title' => 'Upgrade firewall firmware',
    'description' => 'Planned firmware upgrade during the approved maintenance window.',
    'change_type' => 'NORMAL',
    'priority' => 'P2',
    'risk' => 'HIGH',
    'impact' => 'MEDIUM',
    'implementation_plan' => 'Backup configuration, upgrade firmware, reboot and validate HA state.',
    'rollback_plan' => 'Restore previous firmware and configuration if validation fails.',
    'test_plan' => 'Validate HA, routing, VPN and monitoring after the change.',
    'success_criteria' => 'All critical services are healthy and monitoring is green.',
];

$errors = validate_change_payload($valid);
if ($errors !== []) {
    throw new RuntimeException('Valid change payload was rejected: ' . implode(' ', $errors));
}

foreach (change_types() as $type) {
    $copy = $valid;
    $copy['change_type'] = $type;
    if (validate_change_payload($copy) !== []) {
        throw new RuntimeException("Valid change type {$type} was rejected.");
    }
}

foreach (change_statuses() as $status) {
    if (!in_array($status, change_statuses(), true)) {
        throw new RuntimeException("Unknown change status {$status}.");
    }
}

if (!change_transition_allowed('DRAFT', 'ASSESSING')) {
    throw new RuntimeException('DRAFT -> ASSESSING should be allowed.');
}
if (!change_transition_allowed('PENDING_APPROVAL', 'REJECTED')) {
    throw new RuntimeException('PENDING_APPROVAL -> REJECTED should be allowed.');
}
if (!change_transition_allowed('IMPLEMENTING', 'VALIDATING')) {
    throw new RuntimeException('IMPLEMENTING -> VALIDATING should be allowed.');
}
if (!change_transition_allowed('COMPLETED', 'CLOSED')) {
    throw new RuntimeException('COMPLETED -> CLOSED should be allowed.');
}
if (change_transition_allowed('DRAFT', 'APPROVED')) {
    throw new RuntimeException('DRAFT -> APPROVED must not bypass approval.');
}
if (change_transition_allowed('CLOSED', 'IMPLEMENTING')) {
    throw new RuntimeException('CLOSED -> IMPLEMENTING must be rejected.');
}

$invalid = $valid;
$invalid['rollback_plan'] = '';
if (validate_change_payload($invalid) === []) {
    throw new RuntimeException('Missing rollback plan should fail validation.');
}

$invalid = $valid;
$invalid['risk'] = 'UNKNOWN';
if (validate_change_payload($invalid) === []) {
    throw new RuntimeException('Invalid risk should fail validation.');
}

echo "Change validation tests passed\n";
