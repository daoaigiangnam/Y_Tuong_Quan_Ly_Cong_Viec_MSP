<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require_login();

$u=current_user();
$allowedRoles=['ADMIN','IT_LEAD','IT_OWNER','IT_SUPPORT'];
if(!in_array($u['role_code'],$allowedRoles,true)){http_response_code(403);exit('Forbidden');}

function task_e(string $v): string { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
function task_redirect(string $url): never { header('Location: '.$url); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)post('action');
    $taskId=(int)post('task_id',0);
    try{
        if($action==='assign'){
            TaskService::assign($db,$taskId,(int)post('assignee_user_id'),(int)$u['id']);
            flash('success','Task đã được phân công.');
        }elseif($action==='transition'){
            TaskService::transition($db,$taskId,strtoupper(trim((string)post('status'))),(int)$u['id']);
            flash('success','Trạng thái Task đã được cập nhật.');
        }else{ throw new InvalidArgumentException('Action không hợp lệ.'); }
    }catch(Throwable $e){flash('danger',$e->getMessage());}
    task_redirect('tasks.php?id='.$taskId);
}

$taskId=(int)($_GET['id']??0);
$users=$db->query("SELECT u.id,u.full_name,r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.code IN ('IT_SUPPORT','IT_OWNER','IT_LEAD') ORDER BY u.full_name")->fetchAll(PDO::FETCH_ASSOC);

?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tasks - MSP ITSM</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><link href="assets/app.css" rel="stylesheet"></head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container-fluid"><a class="navbar-brand fw-bold" href="index.php?page=dashboard">MSP ITSM</a><span class="text-white small"><?=task_e((string)$u['full_name'])?> · <?=task_e((string)$u['role_name'])?> · <a class="text-white" href="index.php?page=logout">Logout</a></span></div></nav>
<div class="container-fluid"><div class="row"><aside class="col-md-2 sidebar p-3"><a href="index.php?page=dashboard">Dashboard</a><a href="index.php?page=tickets">Tickets</a><a class="fw-bold" href="tasks.php">Tasks</a><a href="index.php?page=contracts">Contracts</a></aside><main class="col-md-10 p-4">
<?php foreach(flashes() as [$type,$msg]):?><div class="alert alert-<?=task_e($type)?>"><?=task_e($msg)?></div><?php endforeach;?>
<?php if($taskId>0):
    $s=$db->prepare("SELECT t.*,tk.ticket_no,tk.subject ticket_subject,c.name customer_name,u.full_name assignee_name,cb.full_name creator_name FROM tasks t LEFT JOIN tickets tk ON tk.id=t.ticket_id LEFT JOIN customers c ON c.id=tk.customer_id LEFT JOIN users u ON u.id=t.assignee_user_id JOIN users cb ON cb.id=t.created_by_user_id WHERE t.id=?");$s->execute([$taskId]);$task=$s->fetch(PDO::FETCH_ASSOC);
    if(!$task){http_response_code(404);exit('Task not found');}
    $s=$db->prepare("SELECT h.*,u.full_name FROM task_history h JOIN users u ON u.id=h.user_id WHERE h.task_id=? ORDER BY h.created_at DESC");$s->execute([$taskId]);$history=$s->fetchAll(PDO::FETCH_ASSOC);
    $next=['NEW'=>['ASSIGNED','IN_PROGRESS','CANCELLED'],'ASSIGNED'=>['IN_PROGRESS','BLOCKED','CANCELLED'],'IN_PROGRESS'=>['BLOCKED','DONE','CANCELLED'],'BLOCKED'=>['ASSIGNED','IN_PROGRESS','CANCELLED'],'DONE'=>[],'CANCELLED'=>[]];
?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><div class="text-muted small">Task</div><h2><?=task_e($task['task_no'])?></h2><div><?=task_e($task['title'])?></div></div><span class="badge bg-primary fs-6"><?=task_e($task['status'])?></span></div>
<div class="row g-4"><div class="col-lg-8"><div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="row g-3"><div class="col-md-8"><label class="text-muted small">Title</label><div class="fw-semibold"><?=task_e($task['title'])?></div></div><div class="col-md-4"><label class="text-muted small">Priority</label><div class="fw-semibold"><?=task_e($task['priority'])?></div></div><div class="col-12"><label class="text-muted small">Description</label><div><?=nl2br(task_e((string)$task['description']))?></div></div><div class="col-md-6"><label class="text-muted small">Ticket</label><div><?php if($task['ticket_id']):?><a href="index.php?page=ticket&id=<?=$task['ticket_id']?>"><?=task_e((string)$task['ticket_no'])?></a> — <?=task_e((string)$task['ticket_subject'])?><?php else:?>-<?php endif;?></div></div><div class="col-md-6"><label class="text-muted small">Customer</label><div><?=task_e((string)($task['customer_name']??'-'))?></div></div><div class="col-md-6"><label class="text-muted small">Due</label><div><?=task_e((string)($task['due_at']??'-'))?></div></div><div class="col-md-6"><label class="text-muted small">Created by</label><div><?=task_e((string)$task['creator_name'])?></div></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Activity / History</div><div class="card-body"><?php foreach($history as $h):?><div class="border-bottom py-2"><div class="small text-muted"><?=task_e($h['created_at'])?> · <?=task_e($h['full_name'])?></div><b><?=task_e($h['event'])?></b> <?=task_e((string)($h['value']??''))?><div class="text-muted small"><?=task_e((string)($h['note']??''))?></div></div><?php endforeach;?></div></div></div>
<div class="col-lg-4"><div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-bold">Assignment</div><div class="card-body"><div class="mb-3">Current: <b><?=task_e((string)($task['assignee_name']??'Unassigned'))?></b></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="assign"><input type="hidden" name="task_id" value="<?=$taskId?>"><select name="assignee_user_id" class="form-select mb-2" required><option value="">Select user...</option><?php foreach($users as $usr):?><option value="<?=$usr['id']?>" <?=$task['assignee_user_id']==$usr['id']?'selected':''?>><?=task_e($usr['full_name'])?> (<?=task_e($usr['code'])?>)</option><?php endforeach;?></select><button class="btn btn-primary w-100">Assign</button></form></div></div><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Status Actions</div><div class="card-body"><?php foreach(($next[$task['status']]??[]) as $status):?><form method="post" class="mb-2"><?=csrf_field()?><input type="hidden" name="action" value="transition"><input type="hidden" name="task_id" value="<?=$taskId?>"><input type="hidden" name="status" value="<?=$status?>"><button class="btn btn-outline-primary w-100"><?=task_e(str_replace('_',' ',$status))?></button></form><?php endforeach;if(empty($next[$task['status']]??[])):?><div class="text-muted">Terminal task — no further transitions.</div><?php endif;?></div></div></div></div>
<?php else:
    $where='';$params=[];
    if($u['role_code']==='IT_SUPPORT'){$where='WHERE t.assignee_user_id=?';$params[]=$u['id'];}
    $status=(string)($_GET['status']??'');if($status){$where.=($where?' AND ':'WHERE').' t.status=?';$params[]=$status;}
    $q=$db->prepare("SELECT t.*,tk.ticket_no,tk.subject ticket_subject,c.name customer_name,u.full_name assignee_name FROM tasks t LEFT JOIN tickets tk ON tk.id=t.ticket_id LEFT JOIN customers c ON c.id=tk.customer_id LEFT JOIN users u ON u.id=t.assignee_user_id $where ORDER BY t.updated_at DESC");$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2>Task Management</h2><a class="btn btn-sm btn-outline-secondary" href="tasks.php">All</a> <a class="btn btn-sm btn-outline-primary" href="tasks.php?status=IN_PROGRESS">In Progress</a> <a class="btn btn-sm btn-outline-danger" href="tasks.php?status=BLOCKED">Blocked</a> <a class="btn btn-sm btn-outline-success" href="tasks.php?status=DONE">Done</a></div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Task</th><th>Ticket</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Due</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a href="tasks.php?id=<?=$r['id']?>"><?=task_e($r['task_no'])?></a><div class="small text-muted"><?=task_e($r['title'])?></div></td><td><?=task_e((string)($r['ticket_no']??'-'))?></td><td><?=task_e((string)($r['customer_name']??'-'))?></td><td><span class="badge text-bg-secondary"><?=task_e($r['priority'])?></span></td><td><span class="badge text-bg-primary"><?=task_e($r['status'])?></span></td><td><?=task_e((string)($r['assignee_name']??'Unassigned'))?></td><td><?=task_e((string)($r['due_at']??'-'))?></td><td><a class="btn btn-sm btn-outline-primary" href="tasks.php?id=<?=$r['id']?>">View</a></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="8" class="text-center text-muted py-5">No tasks found.</td></tr><?php endif;?></tbody></table></div></div>
<?php endif; ?></main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
