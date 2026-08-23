<?php
declare(strict_types=1);

/*
 * The installer is intentionally disabled unless explicitly enabled by the
 * deployment environment. The application DocumentRoot should be public/,
 * so this file is normally not web-accessible at all.
 */
if (getenv('MSP_INSTALLER_ENABLED') !== '1') {
    http_response_code(404);
    exit('Not Found');
}

require __DIR__ . '/app/bootstrap.php';

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $adminPassword = (string)post('admin_password', '');
    $customerPassword = (string)post('customer_password', '');

    if (strlen($adminPassword) < 12 || strlen($customerPassword) < 12) {
        $msg = 'ERROR: Passwords must contain at least 12 characters.';
    } else {
        try {
            $db->beginTransaction();

            $roles = $db->query('SELECT id,code FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach (['ADMIN', 'CUSTOMER'] as $requiredRole) {
                if (!isset($roles[$requiredRole])) {
                    throw new RuntimeException('Required role is missing: ' . $requiredRole);
                }
            }

            $stmt = $db->prepare(
                'INSERT INTO users(username,password_hash,full_name,email,role_id,is_active,created_at)
                 VALUES(?,?,?,?,?,1,?)
                 ON DUPLICATE KEY UPDATE
                    password_hash=VALUES(password_hash),
                    role_id=VALUES(role_id),
                    full_name=VALUES(full_name),
                    email=VALUES(email),
                    is_active=1'
            );

            $stmt->execute([
                'admin',
                password_hash($adminPassword, PASSWORD_DEFAULT),
                'System Administrator',
                'admin@example.com',
                $roles['ADMIN'],
                now(),
            ]);

            $customerId = (int)$db->query(
                "SELECT id FROM customers WHERE code='DEMO' LIMIT 1"
            )->fetchColumn();

            if ($customerId <= 0) {
                throw new RuntimeException('Demo customer is missing. Run database/seed.sql first.');
            }

            $stmt = $db->prepare(
                'INSERT INTO users(username,password_hash,full_name,email,role_id,customer_id,is_active,created_at)
                 VALUES(?,?,?,?,?,?,1,?)
                 ON DUPLICATE KEY UPDATE
                    password_hash=VALUES(password_hash),
                    role_id=VALUES(role_id),
                    customer_id=VALUES(customer_id),
                    full_name=VALUES(full_name),
                    email=VALUES(email),
                    is_active=1'
            );

            $stmt->execute([
                'customer',
                password_hash($customerPassword, PASSWORD_DEFAULT),
                'Demo Customer',
                'customer@example.com',
                $roles['CUSTOMER'],
                $customerId,
                now(),
            ]);

            $db->commit();
            $msg = 'Installation complete. Disable MSP_INSTALLER_ENABLED=1 immediately after initialization.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $msg = 'ERROR: Installation failed.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MSP ITSM Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:620px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h3>MSP ITSM Installer</h3>
            <p class="text-muted">Use only during controlled deployment. Import schema, migrations and seed data first.</p>
            <?php if ($msg): ?>
                <div class="alert alert-info"><?= e($msg) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Admin password</label>
                    <input class="form-control" type="password" name="admin_password" minlength="12" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Customer password</label>
                    <input class="form-control" type="password" name="customer_password" minlength="12" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary">Initialize Users</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
