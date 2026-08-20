<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('msp_itsm');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/contract_policy.php';
require_once __DIR__ . '/problem_policy.php';
require_once __DIR__ . '/change_policy.php';
require_once __DIR__ . '/knowledge_policy.php';
require_once __DIR__ . '/services/ContractService.php';
require_once __DIR__ . '/services/ContractAlertService.php';
require_once __DIR__ . '/services/TicketService.php';
require_once __DIR__ . '/services/ProblemService.php';
require_once __DIR__ . '/services/ChangeService.php';
require_once __DIR__ . '/services/KnowledgeService.php';

$db = db($config);
