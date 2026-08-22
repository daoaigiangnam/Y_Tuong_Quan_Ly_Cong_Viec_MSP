<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

$u = current_user();
if (($u['role_code'] ?? '') === 'CUSTOMER') {
    http_response_code(403);
    exit('Forbidden');
}

function validate_contract(array $data, array $existingCodes = []): array
{
    $errors = [];
    $code = trim((string)($data['contract_code'] ?? ''));
    $customerId = (int)($data['customer_id'] ?? 0);
    $type = trim((string)($data['contract_type'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $start = trim((string)($data['start_date'] ?? ''));
    $end = trim((string)($data['end_date'] ?? ''));
    $types = ['FULL_PACKAGE', 'PER_INCIDENT', 'HOURLY', 'HYBRID'];

    if ($code === '' || strlen($code) > 50) {
        $errors['contract_code'] = 'Contract Code is required and must be <= 50 characters.';
    } elseif (in_array(strtoupper($code), array_map('strtoupper', $existingCodes), true)) {
        $errors['contract_code'] = 'Contract Code already exists.';
    }
    if ($customerId <= 0) {
        $errors['customer_id'] = 'Customer is required.';
    }
    if ($title === '' || strlen($title) < 2 || strlen($title) > 200) {
        $errors['title'] = 'Title must contain 2–200 characters.';
    }
    if (!in_array($type, $types, true)) {
        $errors['contract_type'] = 'Invalid Contract Type.';
    }
    $startDate = DateTime::createFromFormat('Y-m-d', $start);
    $endDate = DateTime::createFromFormat('Y-m-d', $end);
    if (!$startDate || $startDate->format('Y-m-d') !== $start) {
        $errors['start_date'] = 'Invalid start date.';
    }
    if (!$endDate || $endDate->format('Y-m-d') !== $end) {
        $errors['end_date'] = 'Invalid end date.';
    }
    if ($startDate && $endDate && $endDate < $startDate) {
        $errors['end_date'] = 'End date cannot precede start date.';
    }

    return $errors;
}

$demoCustomers = [
    ['id' => 1, 'code' => 'CUS-0001', 'name' => 'ABC Corporation'],
    ['id' => 2, 'code' => 'CUS-0002', 'name' => 'XYZ Trading'],
];
$demoServices = [
    ['id' => 1, 'code' => 'NET', 'name' => 'Network Support'],
    ['id' => 2, 'code' => 'SRV', 'name' => 'Server Support'],
    ['id' => 3, 'code' => 'SEC', 'name' => 'Security'],
];

$errors = [];
$success = '';
$old = [
    'contract_code' => '',
    'customer_id' => '',
    'contract_type' => 'FULL_PACKAGE',
    'title' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+1 year')),
    'status' => 'DRAFT',
    'owner' => '',
    'sales' => '',
    'sla_policy' => '',
    'alert_profile' => 'STANDARD',
    'visibility' => 'CUSTOMER_VIEW',
    'services' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $postedToken)) {
        $errors['csrf'] = 'Invalid CSRF token.';
    } else {
        $old = array_merge($old, $_POST);
        $old['services'] = array_values(array_filter(array_map('intval', (array)($_POST['services'] ?? []))));
        $errors = validate_contract($old, ['DEMO-0001']);
        if (!$errors) {
            $success = 'Contract validated successfully. Database persistence will use the Contract repository/migration in the next implementation step.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contract Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fb}.page{max-width:1280px;margin:30px auto}.card{border:0;box-shadow:0 2px 12px rgba(0,0,0,.06)}.section-title{font-weight:700}.required:after{content:' *';color:#dc3545}.muted{color:#6c757d}
</style>
</head>
<body>
<div class="page px-3">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Contract Management</h1><div class="muted">Module 03 · Configuration-driven contract master</div></div>
    <span class="badge text-bg-warning">IN DEVELOPMENT</span>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if (isset($errors['csrf'])): ?><div class="alert alert-danger"><?= e($errors['csrf']) ?></div><?php endif; ?>

  <div class="card p-4">
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="section-title mb-3">1. Contract Identity</div>
      <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label required">Contract Code</label><input class="form-control <?= isset($errors['contract_code'])?'is-invalid':'' ?>" name="contract_code" value="<?= e((string)$old['contract_code']) ?>"><div class="invalid-feedback"><?= e($errors['contract_code'] ?? '') ?></div></div>
        <div class="col-md-4"><label class="form-label required">Customer</label><select class="form-select <?= isset($errors['customer_id'])?'is-invalid':'' ?>" name="customer_id"><option value="">Select Customer</option><?php foreach($demoCustomers as $c): ?><option value="<?= $c['id'] ?>" <?= (string)$old['customer_id']===(string)$c['id']?'selected':'' ?>><?= e($c['code'].' — '.$c['name']) ?></option><?php endforeach; ?></select><div class="invalid-feedback"><?= e($errors['customer_id'] ?? '') ?></div></div>
        <div class="col-md-4"><label class="form-label required">Contract Type</label><select class="form-select <?= isset($errors['contract_type'])?'is-invalid':'' ?>" name="contract_type"><?php foreach(['FULL_PACKAGE','PER_INCIDENT','HOURLY','HYBRID'] as $t): ?><option value="<?= $t ?>" <?= $old['contract_type']===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select><div class="invalid-feedback"><?= e($errors['contract_type'] ?? '') ?></div></div>
        <div class="col-12"><label class="form-label required">Title</label><input class="form-control <?= isset($errors['title'])?'is-invalid':'' ?>" name="title" value="<?= e((string)$old['title']) ?>"><div class="invalid-feedback"><?= e($errors['title'] ?? '') ?></div></div>
      </div>

      <div class="section-title mb-3">2. Lifecycle</div>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label required">Start Date</label><input type="date" class="form-control <?= isset($errors['start_date'])?'is-invalid':'' ?>" name="start_date" value="<?= e((string)$old['start_date']) ?>"><div class="invalid-feedback"><?= e($errors['start_date'] ?? '') ?></div></div>
        <div class="col-md-3"><label class="form-label required">End Date</label><input type="date" class="form-control <?= isset($errors['end_date'])?'is-invalid':'' ?>" name="end_date" value="<?= e((string)$old['end_date']) ?>"><div class="invalid-feedback"><?= e($errors['end_date'] ?? '') ?></div></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(['DRAFT','PENDING_APPROVAL','ACTIVE','SUSPENDED','CANCELLED'] as $s): ?><option value="<?= $s ?>" <?= $old['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Alert Profile</label><select class="form-select" name="alert_profile"><option value="STANDARD">STANDARD · 90/60/30</option></select></div>
      </div>

      <div class="section-title mb-3">3. Contracted Services</div>
      <div class="row g-2 mb-4"><?php foreach($demoServices as $s): ?><div class="col-md-4"><div class="form-check border rounded p-3"><input class="form-check-input ms-0 me-2" type="checkbox" name="services[]" value="<?= $s['id'] ?>" id="svc<?= $s['id'] ?>" <?= in_array($s['id'], $old['services'], true)?'checked':'' ?>><label class="form-check-label" for="svc<?= $s['id'] ?>"><strong><?= e($s['code']) ?></strong> — <?= e($s['name']) ?></label></div></div><?php endforeach; ?></div>

      <div class="section-title mb-3">4. Policy References</div>
      <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label">SLA Policy</label><input class="form-control" name="sla_policy" placeholder="Policy ID / name" value="<?= e((string)$old['sla_policy']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Contract Owner</label><input class="form-control" name="owner" value="<?= e((string)$old['owner']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Sales Owner</label><input class="form-control" name="sales" value="<?= e((string)$old['sales']) ?>"></div>
      </div>

      <div class="section-title mb-3">5. Customer Visibility</div>
      <div class="row g-3 mb-4"><div class="col-md-4"><label class="form-label">Document / Contract Visibility</label><select class="form-select" name="visibility"><?php foreach(['INTERNAL_ONLY','CUSTOMER_VIEW','CUSTOMER_DOWNLOAD','METADATA_ONLY'] as $v): ?><option value="<?= $v ?>" <?= $old['visibility']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div></div>

      <div class="d-flex justify-content-end gap-2"><button type="reset" class="btn btn-outline-secondary">Reset</button><button class="btn btn-primary" type="submit">Validate Contract</button></div>
    </form>
  </div>
</div>
</body>
</html>
