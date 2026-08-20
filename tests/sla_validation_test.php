<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/sla.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$valid = [
    'policy_code' => 'SLA-001',
    'policy_name' => 'Standard SLA',
    'scope_type' => 'GLOBAL',
    'effective_from' => '2026-01-01 00:00:00',
];
assertSameValue([], validateSlaPolicy($valid), 'valid global policy');

$invalidScope = $valid;
$invalidScope['scope_type'] = 'CUSTOMER';
assertSameValue('Invalid SLA policy scope.', validateSlaPolicy($invalidScope)['scope_type'] ?? null, 'invalid scope');

$missingService = $valid;
$missingService['scope_type'] = 'SERVICE';
assertSameValue('Service is required for SERVICE scope.', validateSlaPolicy($missingService)['service_id'] ?? null, 'service scope requires service');

$missingContract = $valid;
$missingContract['scope_type'] = 'CONTRACT_SERVICE';
$missingContract['service_id'] = 10;
assertSameValue('Contract is required for CONTRACT_SERVICE scope.', validateSlaPolicy($missingContract)['contract_id'] ?? null, 'contract-service scope requires contract');

$badDates = $valid;
$badDates['effective_to'] = '2025-12-31 23:59:59';
assertSameValue('Effective To must be after Effective From.', validateSlaPolicy($badDates)['effective_to'] ?? null, 'effective range');

$badFrom = $valid;
$badFrom['effective_from'] = 'not-a-date';
assertSameValue('Effective From must use YYYY-MM-DD HH:MM:SS.', validateSlaPolicy($badFrom)['effective_from'] ?? null, 'effective from format');

echo "SLA validation tests: PASS\n";
