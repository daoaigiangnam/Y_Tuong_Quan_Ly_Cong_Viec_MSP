<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();
require_permission('customer.view', 'SERVICE');

$user = current_user();
$resource = [];
if (($user['scope_type'] ?? 'GLOBAL') === 'CUSTOMER') {
    $resource['customer_id'] = (int)($user['customer_id'] ?? 0);
}

$q = trim((string)get('q', ''));
$sql = 'SELECT c.*, COUNT(DISTINCT cc.id) contact_count, COUNT(DISTINCT ct.id) ticket_count
        FROM customers c
        LEFT JOIN customer_contacts cc ON cc.customer_id=c.id
        LEFT JOIN tickets ct ON ct.customer_id=c.id';
$params = [];
if (($user['scope_type'] ?? 'GLOBAL') === 'CUSTOMER') {
    $sql .= ' WHERE c.id=?';
    $params[] = (int)$user['customer_id'];
} elseif ($q !== '') {
    $sql .= ' WHERE c.code LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' GROUP BY c.id ORDER BY c.name LIMIT 100';
$s = $db->prepare($sql); $s->execute($params); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customers - MSP ITSM</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head><body><nav class="navbar navbar-dark bg-primary"><div class="container-fluid"><a class="navbar-brand fw-bold" href="portal.php?portal=SERVICE">MSP ITSM</a><span class="text-white small"><?=e($user['full_name'])?> · <?=e($user['role_name'])?></span></div></nav><main class="container-fluid p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2>Customers</h2><div class="text-muted">Customer records within your authorized scope.</div></div><a class="btn btn-outline-primary" href="portal.php?portal=SERVICE">Back to Portal</a></div><form class="row g-2 mb-3"><div class="col-md-6"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search code, name, email, phone"></div><div class="col-auto"><button class="btn btn-primary">Search</button></div></form><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Code</th><th>Customer</th><th>Email</th><th>Phone</th><th>Status</th><th>Contacts</th><th>Tickets</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['code'])?></td><td><?=e($r['name'])?></td><td><?=e($r['email']??'-')?></td><td><?=e($r['phone']??'-')?></td><td><?=e($r['status'])?></td><td><?=$r['contact_count']?></td><td><?=$r['ticket_count']?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
