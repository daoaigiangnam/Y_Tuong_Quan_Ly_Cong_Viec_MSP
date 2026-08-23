<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();
require_permission('service.view', 'SERVICE');

$user = current_user();
$q = trim((string)get('q', ''));
$sql = 'SELECT s.id,s.code,s.name,s.description,s.is_active,COUNT(DISTINCT cs.contract_id) contract_count
        FROM services s LEFT JOIN contract_services cs ON cs.service_id=s.id';
$params=[];
if (($user['scope_type'] ?? 'GLOBAL') === 'SERVICE' && !empty($user['service_id'])) {
    $sql .= ' WHERE s.id=?'; $params[]=(int)$user['service_id'];
} elseif ($q !== '') {
    $sql .= ' WHERE s.code LIKE ? OR s.name LIKE ?'; $like='%'.$q.'%'; array_push($params,$like,$like);
}
$sql .= ' GROUP BY s.id ORDER BY s.name LIMIT 100';
$s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Services - MSP ITSM</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head><body><nav class="navbar navbar-dark bg-primary"><div class="container-fluid"><a class="navbar-brand fw-bold" href="portal.php?portal=SERVICE">MSP ITSM</a><span class="text-white small"><?=e($user['full_name'])?> · <?=e($user['role_name'])?></span></div></nav><main class="container-fluid p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2>Service Catalog</h2><div class="text-muted">Services visible in your authorized scope.</div></div><a class="btn btn-outline-primary" href="portal.php?portal=SERVICE">Back to Portal</a></div><form class="row g-2 mb-3"><div class="col-md-6"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search service code or name"></div><div class="col-auto"><button class="btn btn-primary">Search</button></div></form><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Code</th><th>Service</th><th>Description</th><th>Active</th><th>Contracts</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['code'])?></td><td><?=e($r['name'])?></td><td><?=e($r['description']??'-')?></td><td><?=((int)$r['is_active']===1)?'<span class="badge text-bg-success">ACTIVE</span>':'<span class="badge text-bg-secondary">INACTIVE</span>'?></td><td><?=$r['contract_count']?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
