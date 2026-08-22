<?php

declare(strict_types=1);

function production_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "PRODUCTION READINESS FAILED: {$message}\n");
        exit(1);
    }
}

$requiredFiles = [
    'docs/24_Production_GoLive_Readiness.md',
    'docs/25_Backup_Restore_DR_Runbook.md',
    'docs/23_UAT_Readiness_and_Signoff.md',
    'tests/uat_readiness_test.php',
];

foreach ($requiredFiles as $file) {
    production_assert(is_file(__DIR__ . '/../' . $file), "missing artifact: {$file}");
}

$goLive = file_get_contents(__DIR__ . '/../docs/24_Production_GoLive_Readiness.md');
$dr = file_get_contents(__DIR__ . '/../docs/25_Backup_Restore_DR_Runbook.md');

production_assert($goLive !== false, 'cannot read production go-live runbook');
production_assert($dr !== false, 'cannot read backup/restore/DR runbook');

foreach ([
    'UAT Readiness Gate',
    'Release Regression Gate',
    'Platform Integration Gate',
    'Security/RBAC Gate',
    'Database backup',
    'Rollback',
    'Production evidence',
] as $term) {
    production_assert(stripos($goLive, $term) !== false, "go-live runbook missing: {$term}");
}

foreach ([
    'Backup policy',
    'Restore verification',
    'RPO',
    'RTO',
    'Disaster recovery sequence',
    'DR acceptance criteria',
] as $term) {
    production_assert(stripos($dr, $term) !== false, "DR runbook missing: {$term}");
}

echo "Production Go-Live Readiness checks passed.\n";
