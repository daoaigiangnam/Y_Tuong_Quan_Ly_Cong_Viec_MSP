<?php

declare(strict_types=1);

/**
 * Deployment hardening regression gate.
 *
 * This is intentionally filesystem/source based. It proves that the
 * engineering baseline keeps the deployment boundary explicit before a
 * production environment is provisioned.
 */

function deployment_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('DEPLOYMENT HARDENING TEST FAILED: ' . $message);
    }

    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);

// The public web root must be a dedicated directory.
deployment_assert(
    is_dir($root . '/public') && is_file($root . '/public/index.php'),
    'Dedicated public web root exists'
);

deployment_assert(
    !is_file($root . '/public/install.php'),
    'Installer is not exposed inside the public web root'
);

// The installer may exist in the engineering repository, but production
// deployment must remove/disable it. Keep this distinction explicit.
deployment_assert(
    is_file($root . '/install.php'),
    'Engineering installer exists only outside the public web root'
);

// Runtime configuration must come from environment variables, not committed
// production credentials.
$config = (string)file_get_contents($root . '/app/config.php');
deployment_assert(
    str_contains($config, "getenv('MSP_DB_HOST')") &&
    str_contains($config, "getenv('MSP_DB_NAME')") &&
    str_contains($config, "getenv('MSP_DB_USER')") &&
    str_contains($config, "getenv('MSP_DB_PASS')"),
    'Database configuration is environment-driven'
);
deployment_assert(
    str_contains($config, "getenv('MSP_MAIL_FROM')"),
    'Mail sender configuration is environment-driven'
);

// .env must never be committed as a tracked configuration artifact.
$gitignore = (string)file_get_contents($root . '/.gitignore');
deployment_assert(
    preg_match('/^\\.env$/m', $gitignore) === 1,
    '.env is explicitly ignored by Git'
);

// Uploaded files belong to storage, not the public source tree.
deployment_assert(
    is_dir($root . '/storage/uploads'),
    'Dedicated upload storage directory exists'
);
deployment_assert(
    !is_file($root . '/public/uploads/.gitkeep'),
    'Uploads are not represented as a public web directory'
);

// Session cookie hardening must be enabled at bootstrap.
$bootstrap = (string)file_get_contents($root . '/app/bootstrap.php');
deployment_assert(
    str_contains($bootstrap, "'cookie_httponly' => true") &&
    str_contains($bootstrap, "'cookie_samesite' => 'Lax'") &&
    str_contains($bootstrap, "'cookie_secure' =>"),
    'Session cookies use HttpOnly, SameSite and HTTPS-aware Secure flags'
);

// The production deployment checklist must explicitly cover the installer,
// secrets, backups, logging and rollback procedure.
$runbookPath = $root . '/docs/22_Deployment_Hardening.md';
deployment_assert(is_file($runbookPath), 'Deployment hardening runbook exists');
$runbook = (string)file_get_contents($runbookPath);
foreach (['install.php', 'secrets', 'backups', 'logging', 'rollback'] as $keyword) {
    deployment_assert(
        stripos($runbook, $keyword) !== false,
        "Deployment runbook documents: {$keyword}"
    );
}

echo "DEPLOYMENT HARDENING GATE: PASS\n";
