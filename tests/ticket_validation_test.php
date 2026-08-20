<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/ticket_policy.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$valid = validate_ticket_payload([
    'customer_id' => 1,
    'service_id' => 2,
    'subject' => 'Network connectivity issue',
    'description' => 'Cannot access service.',
    'priority' => 'P2',
]);
assert_true($valid === [], 'valid ticket payload should pass');

assert_true(validate_ticket_payload(['customer_id'=>0,'service_id'=>2,'subject'=>'Valid subject','description'=>'x','priority'=>'P2'])['customer_id'] !== null, 'customer required');
assert_true(validate_ticket_payload(['customer_id'=>1,'service_id'=>0,'subject'=>'Valid subject','description'=>'x','priority'=>'P2'])['service_id'] !== null, 'service required');
assert_true(isset(validate_ticket_payload(['customer_id'=>1,'service_id'=>2,'subject'=>'bad','description'=>'x','priority'=>'P2'])['subject']), 'subject length validation');
assert_true(isset(validate_ticket_payload(['customer_id'=>1,'service_id'=>2,'subject'=>'Valid subject','description'=>'','priority'=>'P2'])['description']), 'description required');
assert_true(isset(validate_ticket_payload(['customer_id'=>1,'service_id'=>2,'subject'=>'Valid subject','description'=>'x','priority'=>'P5'])['priority']), 'priority validation');

assert_true(ticket_transition_allowed('NEW', 'TRIAGED'), 'NEW to TRIAGED');
assert_true(ticket_transition_allowed('IN_PROGRESS', 'RESOLVED'), 'IN_PROGRESS to RESOLVED');
assert_true(ticket_transition_allowed('RESOLVED', 'REOPENED'), 'RESOLVED to REOPENED');
assert_true(!ticket_transition_allowed('CLOSED', 'IN_PROGRESS'), 'CLOSED cannot return to IN_PROGRESS');
assert_true(!ticket_transition_allowed('NEW', 'RESOLVED'), 'NEW cannot skip to RESOLVED');

assert_true(can_view_ticket_note('CUSTOMER', 'CUSTOMER_VISIBLE'), 'customer can see customer-visible note');
assert_true(!can_view_ticket_note('CUSTOMER', 'INTERNAL_ONLY'), 'customer cannot see internal note');
assert_true(can_view_ticket_note('INTERNAL', 'INTERNAL_ONLY'), 'internal user can see internal note');

assert_true(can_reopen_ticket('RESOLVED', 'Still not working'), 'resolved ticket can be reopened with reason');
assert_true(!can_reopen_ticket('RESOLVED', ''), 'reopen requires reason');
assert_true(!can_reopen_ticket('IN_PROGRESS', 'Reason'), 'only resolved ticket can be reopened');

echo "Ticket validation tests passed\n";
