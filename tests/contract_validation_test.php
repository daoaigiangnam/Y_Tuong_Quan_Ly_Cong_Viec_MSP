<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/contract_policy.php';

function contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$valid = [
    'customer_id' => 1,
    'contract_type' => 'FULL_PACKAGE',
    'start_date' => '2026-01-01',
    'end_date' => '2026-12-31',
];

contract_assert(validate_contract_payload($valid) === [], 'valid contract passes');

$missingCustomer = $valid;
$missingCustomer['customer_id'] = 0;
contract_assert(isset(validate_contract_payload($missingCustomer)['customer_id']), 'missing customer is rejected');

$badType = $valid;
$badType['contract_type'] = 'HOURLY';
contract_assert(isset(validate_contract_payload($badType)['contract_type']), 'future unsupported type is rejected until Product enables it');

$badDates = $valid;
$badDates['start_date'] = '2026-12-31';
$badDates['end_date'] = '2026-01-01';
contract_assert(isset(validate_contract_payload($badDates)['date_range']), 'end date before start date is rejected');

contract_assert(contract_transition_allowed('DRAFT', 'PENDING_SIGN'), 'draft to pending sign');
contract_assert(contract_transition_allowed('PENDING_SIGN', 'ACTIVE'), 'pending sign to active');
contract_assert(contract_transition_allowed('ACTIVE', 'EXPIRING'), 'active to expiring');
contract_assert(contract_transition_allowed('ACTIVE', 'RENEWED'), 'active to renewed');
contract_assert(!contract_transition_allowed('EXPIRED', 'ACTIVE'), 'expired cannot directly become active');
contract_assert(!contract_transition_allowed('CANCELLED', 'ACTIVE'), 'cancelled cannot become active');

contract_assert(contract_alert_date('2026-12-31', 90) === '2026-10-02', '90-day alert calculation');
contract_assert(contract_alert_date('2026-12-31', 60) === '2026-11-01', '60-day alert calculation');
contract_assert(contract_alert_date('2026-12-31', 30) === '2026-12-01', '30-day alert calculation');

$defaults = default_contract_alert_rules();
contract_assert(count($defaults) === 3, 'three default alert rules');
contract_assert($defaults[0]['days_before'] === 90, 'default alert 1');
contract_assert($defaults[1]['days_before'] === 60, 'default alert 2');
contract_assert($defaults[2]['days_before'] === 30, 'default alert 3');

echo "Contract validation suite: PASS\n";
