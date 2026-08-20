<?php

declare(strict_types=1);

function knowledge_statuses(): array
{
    return ['DRAFT', 'IN_REVIEW', 'PUBLISHED', 'ARCHIVED'];
}

function knowledge_transition_allowed(string $from, string $to): bool
{
    $map = [
        'DRAFT' => ['IN_REVIEW'],
        'IN_REVIEW' => ['DRAFT', 'PUBLISHED'],
        'PUBLISHED' => ['ARCHIVED', 'IN_REVIEW'],
        'ARCHIVED' => ['DRAFT'],
    ];
    return in_array($to, $map[$from] ?? [], true);
}

function validate_knowledge_payload(array $data): array
{
    $errors = [];
    foreach (['title', 'body', 'category'] as $field) {
        if (trim((string)($data[$field] ?? '')) === '') {
            $errors[] = $field . ' is required.';
        }
    }
    if (isset($data['visibility']) && !in_array(strtoupper((string)$data['visibility']), ['INTERNAL', 'CUSTOMER', 'PUBLIC'], true)) {
        $errors[] = 'Invalid visibility.';
    }
    return $errors;
}
