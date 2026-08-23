<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

$u = current_user();
if (($u['role_code'] ?? '') === 'CUSTOMER') { http_response_code(403); exit('Forbidden'); }

$customers = $db->query("SELECT id, code, name FROM customers WHERE status='ACTIVE' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$services = $db->query("SELECT id, code, name FROM services WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT id, username, full_name FROM users WHERE is_active=1 ORDER BY full_name, username")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';
$old = [
    'contract_no' => '',
    'customer_id' => '',
    'contract_type' => 'FULL_PACKAGE',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+1 year')),
    'value' => '',
    'status' => 'DRAFT',
    'owner_user_id' => '',
    'lead_user_id' => '',
    'sales_user_id' => '',
    'public_notes' => '',
    'internal_notes' => '',
    'services' => [],
    'alert_days' => ['1'=>90,'2'=>60,'3'=>30],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $old = array_merge($old, $_POST);
        $old['services'] = array_values(array_filter(array_map('intval', (array)($_POST['services'] ?? []))));
        $old['alert_days'] = array_filter(array_map('intval', (array)($_POST['alert_days'] ?? [])), static fn(int $v): bool => $v > 0);

        if ((int)$old['customer_id'] < 1) $errors['customer_id'] = 'Customer is required.';
        if (!in_array($old['contract_type'], ['FULL_PACKAGE','PAY_PER_INCIDENT'], true)) $errors['contract_type'] = 'Invalid contract type.';
        if (!in_array($old['status'], ['DRAFT','PENDING_SIGN','ACTIVE','EXPIRING','EXPIRED','RENEWED','CANCELLED'], true)) $errors['status'] = 'Invalid status.';
        $start = DateTime::createFromFormat('Y-m-d', (string)$old['start_date']);
        $end = DateTime::createFromFormat('Y-m-d', (string)$old['end_date']);
        if (!$start || $start->format('Y-m-d') !== $old['start_date']) $errors['start_date'] = 'Invalid start date.';
        if (!$end || $end->format('Y-m-d') !== $old['end_date']) $errors['end_date'] = 'Invalid end date.';
        if ($start && $end && $end < $start) $errors['end_date'] = 'End date cannot precede start date.';
        if ($old['value'] !== '' && !is_numeric($old['value'])) $errors['value'] = 'Contract value must be numeric.';

        if ($errors === []) {
            $id = ContractService::create($db, [
                'contract_no' => trim((string)$old['contract_no']),
                'customer_id' => (int)$old['customer_id'],
                'contract_type' => $old['contract_type'],
                'start_date' => $old['start_date'],
                'end_date' => $old['end_date'],
                'value' => $old['value'] === '' ? null : (float)$old['value'],
                'status' => $old['status'],
                'owner_user_id' => $old['owner_user_id'] !== '' ? (int)$old['owner_user_id'] : null,
                'lead_user_id' => $old['lead_user_id'] !== '' ? (int)$old['lead_user_id'] : null,
                'sales_user_id' => $old['sales_user_id'] !== '' ? (int)$old['sales_user_id'] : null,
                'public_notes' => trim((string)$old['public_notes']) ?: null,
                'internal_notes' => trim((string)$old['internal_notes']) ?: null,
                'alert_days' => $old['alert_days'],
            ]);

            if ($old['services'] !== []) {
                $stmt = $db->prepare('INSERT INTO contract_services(contract_id,service_id) VALUES(?,?)');
                foreach ($old['services'] as $serviceId) $stmt->execute([$id, $serviceId]);
            }

            $success = "Contract #{$id} created successfully.";
            $old['contract_no'] = '';
            $old['services'] = [];
        }
    } catch (Throwable $e) {
        $errors['form'] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contract Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f5f7fb}.page{max-width:1200px;margin:30px auto}.card{border:0;box-shadow:0 2px 12px rgba(0,0,0,.06)}.section-title{font-weight:700}</style>
</head>
<body>
<div class="page px-3">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Contract Management</h1><div class="text-secondary">Operational contract master — database-backed</div></div><span class="badge text-bg-success">ACTIVE IMPLEMENTATION</span></div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (isset($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
<div class="card p-4"><form method="post">
<?= csrf_field() ?>
<div class="section-title mb-3">1. Contract Identity</div>
<div class="row g-3 mb-4">
<div class="col-md-4"><label class="form-label">Contract No</label><input class="form-control" name="contract_no" value="<?= e((string)$old['contract_no']) ?>" placeholder="Auto-generated if empty"></div>
<div class="col-md-4"><label class="form-label">Customer *</label><select class="form-select" name="customer_id"><option value="">Select Customer</option><?php foreach($customers as $c): ?><option value="<?= $c['id'] ?>" <?= (string)$old['customer_id']===(string)$c['id']?'selected':'' ?>><?= e($c['code'].' — '.$c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Contract Type *</label><select class="form-select" name="contract_type"><option value="FULL_PACKAGE" <?= $old['contract_type']==='FULL_PACKAGE'?'selected':'' ?>>FULL_PACKAGE</option><option value="PAY_PER_INCIDENT" <?= $old['contract_type']==='PAY_PER_INCIDENT'?'selected':'' ?>>PAY_PER_INCIDENT</option></select></div>
</div>
<div class="section-title mb-3">2. Lifecycle & Commercial</div>
<div class="row g-3 mb-4">
<div class="col-md-3"><label class="form-label">Start Date *</label><input type="date" class="form-control" name="start_date" value="<?= e((string)$old['start_date']) ?>"></div>
<div class="col-md-3"><label class="form-label">End Date *</label><input type="date" class="form-control" name="end_date" value="<?= e((string)$old['end_date']) ?>"></div>
<div class="col-md-3"><label class="form-label">Value</label><input type="number" step="0.01" min="0" class="form-control" name="value" value="<?= e((string)$old['value']) ?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(['DRAFT','PENDING_SIGN','ACTIVE','EXPIRING','EXPIRED','RENEWED','CANCELLED'] as $s): ?><option value="<?= $s ?>" <?= $old['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
</div>
<div class="section-title mb-3">3. Ownership</div>
<div class="row g-3 mb-4">
<?php foreach(['owner_user_id'=>'Contract Owner','lead_user_id'=>'Service Lead','sales_user_id'=>'Sales Owner'] as $field=>$label): ?><div class="col-md-4"><label class="form-label"><?= $label ?></label><select class="form-select" name="<?= $field ?>"><option value="">Not assigned</option><?php foreach($users as $usr): ?><option value="<?= $usr['id'] ?>" <?= (string)$old[$field]===(string)$usr['id']?'selected':'' ?>><?= e($usr['full_name'].' ('.$usr['username'].')') ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
</div>
<div class="section-title mb-3">4. Contracted Services</div>
<div class="row g-2 mb-4"><?php foreach($services as $s): ?><div class="col-md-4"><label class="border rounded p-3 d-block"><input class="form-check-input me-2" type="checkbox" name="services[]" value="<?= $s['id'] ?>" <?= in_array((int)$s['id'],$old['services'],true)?'checked':'' ?>><?= e($s['code'].' — '.$s['name']) ?></label></div><?php endforeach; ?></div>
<div class="section-title mb-3">5. Contract Alert Rules</div>
<div class="row g-3 mb-4"><?php foreach([1,2,3] as $n): ?><div class="col-md-4"><label class="form-label">Alert #<?= $n ?> — days before</label><input type="number" min="1" class="form-control" name="alert_days[<?= $n ?>]" value="<?= e((string)($old['alert_days'][(string)$n] ?? $old['alert_days'][$n] ?? '')) ?>"></div><?php endforeach; ?></div>
<div class="section-title mb-3">6. Notes</div>
<div class="row g-3 mb-4"><div class="col-md-6"><label class="form-label">Public Notes</label><textarea class="form-control" rows="4" name="public_notes"><?= e((string)$old['public_notes']) ?></textarea></div><div class="col-md-6"><label class="form-label">Internal Notes</label><textarea class="form-control" rows="4" name="internal_notes"><?= e((string)$old['internal_notes']) ?></textarea></div></div>
<div class="d-flex justify-content-end"><button class="btn btn-primary" type="submit">Create Contract</button></div>
</form></div></div>
</body></html>
