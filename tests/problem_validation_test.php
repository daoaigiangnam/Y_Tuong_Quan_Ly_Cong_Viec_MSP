<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/problem_policy.php';

function assert_true_problem(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

assert_true_problem(in_array('REACTIVE', problem_types(), true), 'REACTIVE problem type exists');
assert_true_problem(in_array('PROACTIVE', problem_types(), true), 'PROACTIVE problem type exists');
assert_true_problem(problem_transition_allowed('NEW', 'ASSESSING'), 'NEW -> ASSESSING allowed');
assert_true_problem(problem_transition_allowed('INVESTIGATING', 'ROOT_CAUSE_IDENTIFIED'), 'investigation can identify root cause');
assert_true_problem(problem_transition_allowed('FIX_IMPLEMENTED', 'VALIDATING'), 'implemented fix can enter validation');
assert_true_problem(!problem_transition_allowed('NEW', 'CLOSED'), 'NEW -> CLOSED is blocked');
assert_true_problem(!problem_transition_allowed('ASSESSING', 'CLOSED'), 'ASSESSING -> CLOSED is blocked');

$errors = validate_problem_payload([
    'title' => 'Recurring server restart',
    'description' => 'Server restarts repeatedly during peak hours.',
    'problem_type' => 'REACTIVE',
    'priority' => 'P2',
]);
assert_true_problem($errors === [], 'valid problem payload passes');

$errors = validate_problem_payload([
    'title' => '',
    'description' => '',
    'problem_type' => 'UNKNOWN',
    'priority' => 'P9',
]);
assert_true_problem(count($errors) >= 4, 'invalid payload is rejected');

echo "Problem validation tests PASS\n";
