<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/ticket_policy.php';
require_once __DIR__ . '/../app/services/TicketService.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'msp_itsm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

$db = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function assert_mysql_test(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$suffix = bin2hex(random_bytes(4));
$now = now();

$db->beginTransaction();
try {
    $db->prepare("INSERT INTO roles(code,name) VALUES(?,?)")->execute(["TEST_{$suffix}", 'Ticket Test']);
    $roleId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES(?,?,?,?,?,?,?)")
        ->execute(["ticket_test_{$suffix}", password_hash('test', PASSWORD_DEFAULT), 'Ticket Test User', "ticket_{$suffix}@example.test", $roleId, 1, $now]);
    $userId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO customers(code,name,status,created_at) VALUES(?,?,?,?)")
        ->execute(["TC_{$suffix}", 'Ticket Test Customer', 'ACTIVE', $now]);
    $customerId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO services(code,name,is_active) VALUES(?,?,1)")
        ->execute(["TS_{$suffix}", 'Ticket Test Service']);
    $serviceId = (int)$db->lastInsertId();
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

$ticketId = TicketService::create($db, [
    'customer_id' => $customerId,
    'service_id' => $serviceId,
    'subject' => 'Integration test ticket',
    'description' => 'Created by MySQL integration test.',
    'priority' => 'P2',
    'created_by_user_id' => $userId,
    'requester_user_id' => $userId,
]);

assert_mysql_test($ticketId > 0, 'ticket should be created');

TicketService::transition($db, $ticketId, 'TRIAGED', $userId);
TicketService::transition($db, $ticketId, 'ASSIGNED', $userId);
TicketService::transition($db, $ticketId, 'IN_PROGRESS', $userId);
TicketService::transition($db, $ticketId, 'RESOLVED', $userId, 'Work completed.');
TicketService::transition($db, $ticketId, 'REOPENED', $userId, 'Customer reports issue persists.');
TicketService::transition($db, $ticketId, 'IN_PROGRESS', $userId);
TicketService::transition($db, $ticketId, 'RESOLVED', $userId, 'Second resolution completed.');
TicketService::transition($db, $ticketId, 'CLOSED', $userId, 'Customer accepted resolution.');

TicketService::comment($db, $ticketId, $userId, 'Customer-visible update.');
TicketService::comment($db, $ticketId, $userId, 'Internal diagnostic note.', true);

$stmt = $db->prepare('SELECT status,reopen_count,first_response_at,resolved_at,closed_at FROM tickets WHERE id=?');
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();
assert_mysql_test($ticket['status'] === 'CLOSED', 'ticket should end CLOSED');
assert_mysql_test((int)$ticket['reopen_count'] === 1, 'reopen count should be 1');
assert_mysql_test($ticket['first_response_at'] !== null, 'first response timestamp should be populated');
assert_mysql_test($ticket['resolved_at'] !== null, 'resolved timestamp should be populated');
assert_mysql_test($ticket['closed_at'] !== null, 'closed timestamp should be populated');

$stmt = $db->prepare('SELECT visibility,COUNT(*) total FROM ticket_comments WHERE ticket_id=? GROUP BY visibility');
$stmt->execute([$ticketId]);
$visibility = [];
foreach ($stmt->fetchAll() as $row) {
    $visibility[$row['visibility']] = (int)$row['total'];
}
assert_mysql_test(($visibility['CUSTOMER_VISIBLE'] ?? 0) === 1, 'one customer-visible comment expected');
assert_mysql_test(($visibility['INTERNAL_ONLY'] ?? 0) === 1, 'one internal comment expected');

echo "Ticket MySQL integration tests passed\n";
