<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

$user = current_user();
$requestedPortal = strtoupper((string)get('portal', ''));
$defaultPortal = (($user['role_code'] ?? '') === 'CUSTOMER') ? 'CUSTOMER' : 'SERVICE';
$portal = $requestedPortal !== '' ? $requestedPortal : $defaultPortal;

if (!in_array($portal, ['SERVICE', 'CUSTOMER'], true)) {
    http_response_code(404);
    exit('Portal not found');
}

$permissions = (array)($user['permissions'] ?? []);
$portalAllowed = rbac_portal_allowed($user, $portal);
if (!$portalAllowed) {
    http_response_code(403);
    exit('Portal access denied');
}

$canDashboard = rbac_authorize($user, $permissions, 'dashboard.view', $portal) === 'ALLOW';
$canTicketRead = rbac_authorize($user, $permissions, 'ticket.view', $portal) === 'ALLOW';
$canTicketCreate = rbac_authorize($user, $permissions, 'ticket.create', $portal) === 'ALLOW';
$canContractRead = rbac_authorize($user, $permissions, 'contract.view', $portal) === 'ALLOW';
$canCustomerRead = rbac_authorize($user, $permissions, 'customer.view', $portal) === 'ALLOW';
$canServiceRead = rbac_authorize($user, $permissions, 'service.view', $portal) === 'ALLOW';

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
<div class="text-white small"><?=e($user['full_name'])?> · <?=e($user['role_name'])?> · <?=e((string)($user['scope_type'] ?? ''))?> · <a class="text-white" href="?page=logout">Logout</a></div>
</div>
</nav>
<div class="container-fluid"><div class="row">
<aside class="col-md-2 sidebar p-3">
<div class="text-muted small mb-2">PORTAL</div>
<a href="portal.php?portal=<?=e($portal)?>"><i class="bi bi-grid"></i> Dashboard</a>
<?php if ($canTicketRead): ?><a href="?page=tickets"><i class="bi bi-ticket"></i> Tickets</a><?php endif; ?>
<?php if ($canTicketCreate): ?><a href="?page=ticket_create"><i class="bi bi-plus-circle"></i> Create Ticket</a><?php endif; ?>
<?php if ($canContractRead): ?><a href="?page=contracts"><i class="bi bi-file-earmark-text"></i> Contracts</a><?php endif; ?>
<?php if ($canCustomerRead && $portal === 'SERVICE'): ?><a href="?page=customers"><i class="bi bi-people"></i> Customers</a><?php endif; ?>
<?php if ($canServiceRead): ?><a href="?page=services"><i class="bi bi-box"></i> Services</a><?php endif; ?>
</aside>
<main class="col-md-10 p-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<div><h2 class="mb-1"><?=e($title)?></h2><div class="text-muted">RBAC-controlled workspace</div></div>
<span class="badge text-bg-light border"><?=e($portal)?> · <?=e((string)($user['scope_type'] ?? ''))?></span>
</div>
<?php if (!$canDashboard): ?>
<div class="alert alert-warning">You do not have permission to view the dashboard.</div>
<?php else: ?>
<div class="row g-3">
<?php if ($canTicketRead): ?><div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-ticket fs-3"></i><h5 class="mt-2">Tickets</h5><p class="text-muted">View tickets within your authorized scope.</p><a class="btn btn-outline-primary" href="?page=tickets">Open Tickets</a></div></div></div><?php endif; ?>
<?php if ($canTicketCreate): ?><div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-plus-circle fs-3"></i><h5 class="mt-2">Request Service</h5><p class="text-muted">Create a service ticket within your authorized scope.</p><a class="btn btn-primary" href="?page=ticket_create">Create Ticket</a></div></div></div><?php endif; ?>
<?php if ($canContractRead): ?><div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-file-earmark-text fs-3"></i><h5 class="mt-2">Contracts</h5><p class="text-muted">Access contract information when authorized.</p><a class="btn btn-outline-primary" href="?page=contracts">Open Contracts</a></div></div></div><?php endif; ?>
<?php if ($canCustomerRead && $portal === 'SERVICE'): ?><div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-people fs-3"></i><h5 class="mt-2">Customers</h5><p class="text-muted">Manage customer records according to permission and scope.</p><a class="btn btn-outline-primary" href="?page=customers">Open Customers</a></div></div></div><?php endif; ?>
<?php if ($canServiceRead): ?><div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><i class="bi bi-box fs-3"></i><h5 class="mt-2">Services</h5><p class="text-muted">View services available in the authorized catalog.</p><a class="btn btn-outline-primary" href="?page=services">Open Services</a></div></div></div><?php endif; ?>
</div>
<?php endif; ?>
</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
