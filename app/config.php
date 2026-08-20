<?php
declare(strict_types=1);

return [
    'app_name' => 'MSP ITSM',
    'base_url' => '',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'db' => [
        'host' => getenv('MSP_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('MSP_DB_PORT') ?: '3306',
        'name' => getenv('MSP_DB_NAME') ?: 'msp_itsm',
        'user' => getenv('MSP_DB_USER') ?: 'root',
        'pass' => getenv('MSP_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from' => getenv('MSP_MAIL_FROM') ?: 'itsm@example.com',
        'from_name' => getenv('MSP_MAIL_FROM_NAME') ?: 'MSP ITSM',
    ],
    'upload_dir' => __DIR__ . '/../storage/uploads',
    'max_upload_mb' => 10,
];
