<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

$user = current_user();
$portal = strtoupper((string)get('portal', $user['role_code'] === 'CUSTOMER' ? 'CUSTOMER' : 'SERVICE'));
if (!in_array($portal, ['SERVICE', 'CUSTOMER'], true)) {
    http_response_code(404);
    exit('Portal not found');
}

if ($portal === 'CUSTOMER' && ($user['role_code'] ?? '') !== 'CUSTOMER') {
    http_response_code(403);
    exit('Customer portal requires customer identity');
}

$permissions = (array)($user['permissions'] ?? []);
$canDashboard = rbac_authorize($user, $permissions, 'dashboard.view', $portal) === 'ALLOW';
$canTicketRead = rbac_authorize($user, $permissions, 'ticket.view', $portal) === 'ALLOW';
$canTicketCreate = rbac_authorize($user, $permissions, 'ticket.create', $portal) === 'ALLOW';
$canContractRead = rbac_authorize($user, $permissions, 'contract.view', $portal) === 'ALLOW';

$title = $portal === 'CUSTOMER' ? 'Customer Portal' : 'Service Portal';
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> - MSP ITSM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
<div class="container-fluid">
<a class="navbar-brand fw-bold" href="portal.php?portal=<?=e($portal)?>">MSP ITSM</a>
<div class="text-white small"><?=e($user['full_name'])?> · <?=e($user['role_name'])?> · <a class="text-white" href="?page=logout">Logout</a></div>
</div>
</nav>
<div class="container-fluid"><div class="row">
<aside class="col-md-2 sidebar p-3">
<div class="text-muted small mb-2">PORTAL</div>
<?php if ($canDashboard): ?><a href="portal.php?portal=<?=e($portal)?>"><i class="bi bi-grid"></i> Dashboard</a><?php endif; ?>
<?php if ($canTicketRead): ?><a href="?page=tickets"><i class="bi bi-ticket"></i> Tickets</a><?php endif; ?>
<?php if ($canTicketCreate): ?><a href="?page=ticket_create"><i class="bi bi-plus-circle"></i> Create Ticket</a><?php endif; ?>
<?php if ($canContractRead): ?><a href="?page=contracts"><i class="bi bi-file-earmark-text"></i> Contracts</a><?php endif; ?>
</aside>
<main class="col-md-10 p-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<div><h2 class="mb-1"><?=e($title)?></h2><div class="text-muted">RBAC-controlled workspace</div></div>
<span class="badge text-bg-light border"><?=e($portal)?></span>
</div>
<?php if (!$canDashboard): ?>
<div class="alert alert-warning">You do not have permission to view the dashboard.</div>
<?php else: ?>
<div class="row g-3">
<div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-ticket fs-3"></i><h5 class="mt-2">Tickets</h5><p class="text-muted">View and manage service requests according to your permissions.</p><?php if ($canTicketRead): ?><a class="btn btn-outline-primary" href="?page=tickets">Open Tickets</a><?php else: ?><span class="text-muted">Not permitted</span><?php endif; ?></div></div></div>
<div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-plus-circle fs-3"></i><h5 class="mt-2">Request Service</h5><p class="text-muted">Create a new service ticket within your allowed scope.</p><?php if ($canTicketCreate): ?><a class="btn btn-primary" href="?page=ticket_create">Create Ticket</a><?php else: ?><span class="text-muted">Not permitted</span><?php endif; ?></div></div></div>
<div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-file-earmark-text fs-3"></i><h5 class="mt-2">Contracts</h5><p class="text-muted">Access contract information only when authorized.</p><?php if ($canContractRead): ?><a class="btn btn-outline-primary" href="?page=contracts">Open Contracts</a><?php else: ?><span class="text-muted">Not permitted</span><?php endif; ?></div></div></div>
</div>
<?php endif; ?>
</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
