<?php

declare(strict_types=1);

final class TaskPolicy
{
    public const CREATE_ON_SUPPORT_ASSIGNMENT = 'CREATE_TASK_ON_SUPPORT_ASSIGNMENT';

    public static function normalize(array $input): array
    {
        $enabled = $input['enabled'] ?? true;
        if (!is_bool($enabled) && !in_array($enabled, [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Task policy enabled must be boolean.');
        }

        $trigger = (string)($input['trigger_event'] ?? 'TICKET_ASSIGNMENT');
        if ($trigger !== 'TICKET_ASSIGNMENT') {
            throw new InvalidArgumentException('Unsupported task creation trigger.');
        }

        return [
            'code' => self::CREATE_ON_SUPPORT_ASSIGNMENT,
            'enabled' => (bool)$enabled,
            'trigger_event' => $trigger,
        ];
    }

    public static function shouldCreateOnAssignment(array $policy, ?int $ticketId, ?int $assigneeUserId): bool
    {
        return (bool)($policy['enabled'] ?? false)
            && ($policy['trigger_event'] ?? null) === 'TICKET_ASSIGNMENT'
            && $ticketId !== null
            && $ticketId > 0
            && $assigneeUserId !== null
            && $assigneeUserId > 0;
    }
}
