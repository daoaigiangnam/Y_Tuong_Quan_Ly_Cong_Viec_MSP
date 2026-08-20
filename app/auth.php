<?php
declare(strict_types=1);

function require_login(): void { if (!current_user()) redirect('?page=login'); }
function require_role(array $roles): void { require_login(); if (!in_array(current_user()['role_code'], $roles, true)) { http_response_code(403); exit('Forbidden'); } }
function login_user(PDO $db, string $username, string $password): bool {
    $s=$db->prepare('SELECT u.*,r.code role_code,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.is_active=1 LIMIT 1');
    $s->execute([$username]); $u=$s->fetch();
    if (!$u || !password_verify($password,$u['password_hash'])) return false;
    unset($u['password_hash']); $_SESSION['user']=$u; session_regenerate_id(true); return true;
}
function logout_user(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']??'Lax']); } session_destroy(); }
