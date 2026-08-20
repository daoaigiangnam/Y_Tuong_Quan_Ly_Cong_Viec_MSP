<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][] = [$type, $message]; }
function flashes(): array { $x = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $x; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token'); } }
function post(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $default; }
function get(string $key, mixed $default = null): mixed { return $_GET[$key] ?? $default; }
function now(): string { return date('Y-m-d H:i:s'); }
function badge(string $text, string $class = 'secondary'): string { return '<span class="badge text-bg-' . e($class) . '">' . e($text) . '</span>'; }
function ticket_badge(string $status): string {
    $map = [
        'NEW'=>'primary', 'TRIAGED'=>'info', 'ASSIGNED'=>'info', 'IN_PROGRESS'=>'warning',
        'PENDING_CUSTOMER'=>'secondary', 'PENDING_VENDOR'=>'secondary', 'PENDING_INTERNAL'=>'secondary',
        'RESOLVED'=>'success', 'CLOSED'=>'dark', 'REOPENED'=>'danger'
    ];
    return badge($status, $map[$status] ?? 'secondary');
}
function priority_badge(string $p): string { $map=['P1'=>'danger','P2'=>'warning','P3'=>'info','P4'=>'secondary']; return badge($p,$map[$p]??'secondary'); }
function mail_notice(array $config, string $to, string $subject, string $body, ?string $cc = null): bool {
    $headers = 'From: ' . $config['mail']['from_name'] . ' <' . $config['mail']['from'] . "\r\n";
    if ($cc) $headers .= 'Cc: ' . $cc . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
function audit(PDO $db, ?int $userId, string $action, string $entity, int $entityId, array $meta=[]): void {
    $s=$db->prepare('INSERT INTO audit_logs(user_id,action,entity,entity_id,meta,created_at) VALUES(?,?,?,?,?,?)');
    $s->execute([$userId,$action,$entity,$entityId,json_encode($meta,JSON_UNESCAPED_UNICODE),now()]);
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
