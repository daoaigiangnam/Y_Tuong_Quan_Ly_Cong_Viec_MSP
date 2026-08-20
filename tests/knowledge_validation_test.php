<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/knowledge_policy.php';

function expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

expect(in_array('DRAFT', knowledge_statuses(), true), 'DRAFT missing');
expect(knowledge_transition_allowed('DRAFT', 'IN_REVIEW'), 'draft review transition missing');
expect(knowledge_transition_allowed('IN_REVIEW', 'PUBLISHED'), 'review publish transition missing');
expect(!knowledge_transition_allowed('DRAFT', 'PUBLISHED'), 'draft must not bypass review');
expect(knowledge_transition_allowed('PUBLISHED', 'ARCHIVED'), 'archive transition missing');
expect(knowledge_transition_allowed('ARCHIVED', 'DRAFT'), 'reopen transition missing');
expect(validate_knowledge_payload(['title'=>'','body'=>'','category'=>'']) !== [], 'validation must reject empty payload');
expect(validate_knowledge_payload(['title'=>'VPN FAQ','body'=>'Steps','category'=>'Network']) === [], 'valid payload rejected');

echo "Knowledge validation tests passed\n";
