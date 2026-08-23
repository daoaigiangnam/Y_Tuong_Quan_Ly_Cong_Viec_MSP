<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac_policy.php';

function load_user_permissions(PDO $db, int $roleId): array
{
    $s = $db->prepare(
        'SELECT p.code
         FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?
         ORDER BY p.code'
    );
    $s->execute([$roleId]);
    return array_values(array_map('strval', $s->fetchAll(PDO::FETCH_COLUMN)));
}

function require_login(): void
{
    if (!current_user()) {
        redirect('?page=login');
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role_code'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function require_permission(string $permission, string $portal = 'SERVICE', array $resource = []): void
{
    require_login();

    $user = current_user();
    $result = rbac_authorize(
        $user,
        (array)($user['permissions'] ?? []),
        $permission,
        $portal,
        $resource
    );

    if ($result !== 'ALLOW') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function login_user(PDO $db, string $username, string $password): bool
{
    $s = $db->prepare(
        'SELECT u.*, r.code role_code, r.name role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.username = ? AND u.is_active = 1
         LIMIT 1'
    );
    $s->execute([$username]);
    $u = $s->fetch(PDO::FETCH_ASSOC);

    if (!$u || !password_verify($password, $u['password_hash'])) {
        return false;
    }

    $u['permissions'] = load_user_permissions($db, (int)$u['role_id']);
    unset($u['password_hash']);

    session_regenerate_id(true);
    $_SESSION['user'] = $u;
    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}
