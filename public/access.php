<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

$u = current_user();

if (($u['role_code'] ?? '') === 'CUSTOMER') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../app/rbac_policy.php';

$permissions = [
    'customer.view', 'service.view', 'contract.view', 'sla.view',
];

$sampleCustomer = ['customer_id' => (int)($u['customer_id'] ?? 1)];
$decision = rbac_authorize($u + ['portal_type' => 'INTERNAL', 'is_active' => 1], $permissions, 'customer.view', 'INTERNAL', $sampleCustomer);

?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Authorization Console - MSP ITSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand">MSP ITSM · Authorization</span>
        <span class="text-white"><?= e((string)($u['full_name'] ?? 'User')) ?></span>
    </div>
</nav>
<main class="container py-4">
    <h2>User / Role / Permission</h2>
    <p class="text-muted">Development console for RBAC, Portal and Scope decisions.</p>

    <div class="row g-3">
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Role</div><h4><?= e((string)($u['role_code'] ?? '-')) ?></h4></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Portal</div><h4>INTERNAL</h4></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Authorization</div><h4 class="text-success"><?= e($decision) ?></h4></div></div></div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-bold">Permission Matrix — current development baseline</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Permission</th><th>Current Test Grant</th><th>Purpose</th></tr></thead>
                <tbody>
                <?php foreach ($permissions as $permission): ?>
                    <tr><td><code><?= e($permission) ?></code></td><td><span class="badge text-bg-success">GRANTED</span></td><td>Server-side permission candidate</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        This screen is an authorization development console. Final User/Role CRUD, permission catalog and customer-scope administration will be wired to the production data model after the Product decisions in Module 05 are finalized.
    </div>
</main>
</body>
</html>
