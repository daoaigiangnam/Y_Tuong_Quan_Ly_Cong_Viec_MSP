<?php
declare(strict_types=1);

function valid_service_code(string $code): bool { return (bool)preg_match('/^[A-Z0-9][A-Z0-9._-]{1,49}$/', $code); }
function valid_service_name(string $name): bool { $n=mb_strlen(trim($name)); return $n>=2 && $n<=150; }

$tests = [
    'valid code' => valid_service_code('NET_SUPPORT_01') === true,
    'lowercase rejected' => valid_service_code('net_support') === false,
    'invalid code rejected' => valid_service_code('-BAD') === false,
    'short name rejected' => valid_service_name('A') === false,
    'normal name accepted' => valid_service_name('Network Support') === true,
    'empty name rejected' => valid_service_name('') === false,
];

foreach ($tests as $name=>$ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
    echo "PASS: {$name}\n";
}
echo 'ALL SERVICE VALIDATION TESTS PASSED'.PHP_EOL;
