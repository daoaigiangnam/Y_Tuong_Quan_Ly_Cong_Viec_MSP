<?php

declare(strict_types=1);

function validate_contract(array $data, array $existingCodes = []): array
{
    $errors = [];
    $code = trim((string)($data['contract_code'] ?? ''));
    $customerId = (int)($data['customer_id'] ?? 0);
    $type = trim((string)($data['contract_type'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $start = trim((string)($data['start_date'] ?? ''));
    $end = trim((string)($data['end_date'] ?? ''));
    $types = ['FULL_PACKAGE', 'PER_INCIDENT', 'HOURLY', 'HYBRID'];

    if ($code === '' || strlen($code) > 50) $errors['contract_code'] = 'invalid';
    elseif (in_array(strtoupper($code), array_map('strtoupper', $existingCodes), true)) $errors['contract_code'] = 'duplicate';
    if ($customerId <= 0) $errors['customer_id'] = 'required';
    if ($title === '' || strlen($title) < 2 || strlen($title) > 200) $errors['title'] = 'invalid';
    if (!in_array($type, $types, true)) $errors['contract_type'] = 'invalid';
    $startDate = DateTime::createFromFormat('Y-m-d', $start);
    $endDate = DateTime::createFromFormat('Y-m-d', $end);
    if (!$startDate || $startDate->format('Y-m-d') !== $start) $errors['start_date'] = 'invalid';
    if (!$endDate || $endDate->format('Y-m-d') !== $end) $errors['end_date'] = 'invalid';
    if ($startDate && $endDate && $endDate < $startDate) $errors['end_date'] = 'before_start';
    return $errors;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$valid = [
    'contract_code' => 'HD-0001',
    'customer_id' => 1,
    'contract_type' => 'FULL_PACKAGE',
    'title' => 'Managed IT Support',
    'start_date' => '2026-01-01',
    'end_date' => '2026-12-31',
];
assert_true(validate_contract($valid) === [], 'valid contract passes');
assert_true(isset(validate_contract($valid, ['HD-0001'])['contract_code']), 'duplicate contract code is rejected');
$badDates = $valid;
$badDates['start_date'] = '2026-12-31';
$badDates['end_date'] = '2026-01-01';
assert_true(isset(validate_contract($badDates)['end_date']), 'end date before start date is rejected');
$badType = $valid;
$badType['contract_type'] = 'UNKNOWN';
assert_true(isset(validate_contract($badType)['contract_type']), 'unsupported contract type is rejected');
$missingCustomer = $valid;
$missingCustomer['customer_id'] = 0;
assert_true(isset(validate_contract($missingCustomer)['customer_id']), 'missing customer is rejected');
$badTitle = $valid;
$badTitle['title'] = 'X';
assert_true(isset(validate_contract($badTitle)['title']), 'short title is rejected');

echo "Contract validation suite: PASS\n";
