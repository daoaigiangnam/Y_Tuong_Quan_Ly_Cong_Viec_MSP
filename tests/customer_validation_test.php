<?php
declare(strict_types=1);

function assert_true(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
function valid_code(string $code): bool { return (bool)preg_match('/^[A-Z0-9][A-Z0-9._-]{1,39}$/', $code); }
function valid_name(string $name): bool { $n=mb_strlen($name); return $n>=2 && $n<=190; }
function valid_email(string $email): bool { return $email==='' || filter_var($email,FILTER_VALIDATE_EMAIL)!==false; }

assert_true(valid_code('CUS001'), 'accept valid customer code');
assert_true(valid_code('ABC-001'), 'accept hyphenated customer code');
assert_true(!valid_code('a'), 'reject one-character customer code');
assert_true(!valid_code('CUS 001'), 'reject customer code containing spaces');
assert_true(valid_name('ABC Corporation'), 'accept valid customer name');
assert_true(!valid_name('A'), 'reject too-short customer name');
assert_true(valid_email(''), 'allow empty optional email');
assert_true(valid_email('support@example.com'), 'accept valid email');
assert_true(!valid_email('not-an-email'), 'reject invalid email');
echo "ALL CUSTOMER VALIDATION TESTS PASSED\n";
