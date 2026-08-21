<?php

declare(strict_types=1);

/**
 * Security regression checks for shared input/output hardening helpers.
 *
 * This checkpoint is intentionally dependency-free: it validates the security
 * primitives used by the application without requiring a live database or
 * browser session.
 */

function security_input_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('SECURITY INPUT TEST FAILED: ' . $message);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$helpersPath = $root . '/app/helpers.php';

security_input_assert(is_file($helpersPath), 'Shared security helper file exists');

require_once $helpersPath;

// XSS/output encoding regression.
$payload = '<script>alert("xss")</script>" & \'quoted\'';
$escaped = e($payload);
security_input_assert($escaped === htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'), 'e() applies HTML escaping with ENT_QUOTES and UTF-8');
security_input_assert(!str_contains($escaped, '<script>'), 'e() neutralizes executable HTML/script markup');
security_input_assert(str_contains($escaped, '&quot;') && str_contains($escaped, '&#039;'), 'e() escapes both double and single quotes');

// Badge helpers must not turn user-controlled text/class values into markup.
$badge = badge($payload, '" onmouseover="alert(1)');
security_input_assert(!str_contains($badge, '<script>'), 'badge() escapes badge text');
security_input_assert(!str_contains($badge, 'onmouseover="alert(1)'), 'badge() escapes badge CSS class attribute content');

// CSRF primitive checks.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

unset($_SESSION['_csrf']);
$token = csrf_token();
security_input_assert(strlen($token) === 64 && ctype_xdigit($token), 'CSRF token is a 32-byte random value encoded as 64 hex characters');
security_input_assert(hash_equals($token, csrf_token()), 'CSRF token remains stable within the session');

$field = csrf_field();
security_input_assert(str_contains($field, 'name="_csrf"'), 'csrf_field() emits the expected CSRF field name');
security_input_assert(str_contains($field, 'value="' . e($token) . '"'), 'csrf_field() emits the session CSRF token through HTML escaping');

$helperSource = (string)file_get_contents($helpersPath);
security_input_assert(str_contains($helperSource, 'htmlspecialchars((string)$value, ENT_QUOTES, \'UTF-8\')'), 'Shared output helper uses htmlspecialchars with strict quote escaping');
security_input_assert(str_contains($helperSource, 'hash_equals($_SESSION[\'_csrf\'] ?? \'\', $_POST[\'_csrf\'] ?? \'\')'), 'CSRF verification uses constant-time hash_equals comparison');
security_input_assert(str_contains($helperSource, 'random_bytes(32)'), 'CSRF token generation uses cryptographically secure random_bytes');

fwrite(STDOUT, "Security input hardening checkpoint PASS\n");
