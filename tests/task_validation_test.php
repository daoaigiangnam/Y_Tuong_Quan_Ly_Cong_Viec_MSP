<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/task_policy.php';

$policy = TaskPolicy::normalize([
    'enabled' => true,
    'trigger_event' => 'TICKET_ASSIGNMENT',
]);

if ($policy['code'] !== TaskPolicy::CREATE_ON_SUPPORT_ASSIGNMENT) {
    throw new RuntimeException('Task policy code mismatch.');
}
if (!TaskPolicy::shouldCreateOnAssignment($policy, 100, 200)) {
    throw new RuntimeException('Enabled task creation policy should create a task on valid assignment.');
}
if (TaskPolicy::shouldCreateOnAssignment($policy, null, 200)) {
    throw new RuntimeException('Task creation must not happen without a ticket.');
}
if (TaskPolicy::shouldCreateOnAssignment(['enabled' => false, 'trigger_event' => 'TICKET_ASSIGNMENT'], 100, 200)) {
    throw new RuntimeException('Disabled task creation policy must not create a task.');
}

try {
    TaskPolicy::normalize(['enabled' => true, 'trigger_event' => 'MANUAL_ONLY']);
    throw new RuntimeException('Unsupported trigger should be rejected.');
} catch (InvalidArgumentException $e) {
    // expected
}

echo "Task policy validation tests passed\n";
