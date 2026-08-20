<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  try{
    $db->beginTransaction();
    $roles=$db->query('SELECT id,code FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $adminPass=(string)post('admin_password','ChangeMe123!');
    $s=$db->prepare('INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at) VALUES(?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role_id=VALUES(role_id),full_name=VALUES(full_name),email=VALUES(email)');
    $s->execute(['admin',password_hash($adminPass,PASSWORD_DEFAULT),'System Administrator','admin@example.com',$roles['ADMIN'],now()]);
    $cid=(int)$db->query("SELECT id FROM customers WHERE code='DEMO' LIMIT 1")->fetchColumn();
    $s=$db->prepare('INSERT INTO users(username,password_hash,full_name,email,role_id,customer_id,is_active,created_at) VALUES(?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role_id=VALUES(role_id),customer_id=VALUES(customer_id)');
    $s->execute(['customer',password_hash((string)post('customer_password','Customer123!'),PASSWORD_DEFAULT),'Demo Customer','customer@example.com',$roles['CUSTOMER'],$cid,now()]);
    $db->commit(); $msg='Installation complete. Delete install.php before production.';
  }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); $msg='ERROR: '.$e->getMessage(); }
}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MSP ITSM Installer</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5" style="max-width:620px"><div class="card shadow-sm"><div class="card-body p-4"><h3>MSP ITSM Installer</h3><p class="text-muted">Run after importing database/schema.sql, seed.sql and migrations/002_customer_user.sql.</p><?php if($msg):?><div class="alert alert-info"><?=e($msg)?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="mb-3"><label class="form-label">Admin password</label><input class="form-control" type="password" name="admin_password" value="ChangeMe123!"></div><div class="mb-3"><label class="form-label">Customer demo password</label><input class="form-control" type="password" name="customer_password" value="Customer123!"></div><button class="btn btn-primary">Initialize Users</button></form></div></div></div></body></html>
