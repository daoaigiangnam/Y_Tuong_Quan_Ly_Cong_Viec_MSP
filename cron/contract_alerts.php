<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$result=ContractAlertService::run($db,$config);
echo '['.date('Y-m-d H:i:s').'] '.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
