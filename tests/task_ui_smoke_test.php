<?php
declare(strict_types=1);

$path=__DIR__.'/../public/tasks.php';
if(!is_file($path)){throw new RuntimeException('Task UI route is missing.');}
$source=file_get_contents($path);
if($source===false){throw new RuntimeException('Unable to read Task UI route.');}
$required=[
    'require_login()',
    'TaskService::assign',
    'TaskService::transition',
    'csrf_field()',
    'Task Management',
    'Activity / History',
    'ticket_id',
    'assignee_user_id',
    'IN_PROGRESS',
    'BLOCKED',
    'DONE',
    'CANCELLED',
];
foreach($required as $needle){
    if(strpos($source,$needle)===false){
        throw new RuntimeException("Task UI smoke check failed: missing {$needle}");
    }
}

if(strpos($source,"role_code']==='CUSTOMER")!==false || strpos($source,"role_code']==\"CUSTOMER\"")!==false){
    throw new RuntimeException('Customer role must not be allowed into internal Task UI.');
}

if(strpos($source,'csrf_field()')===false || strpos($source,'verify_csrf()')===false){
    throw new RuntimeException('Task UI POST actions must use CSRF protection.');
}

echo "Task UI smoke tests passed.\n";
