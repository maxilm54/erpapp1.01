<?php

return [
    'master' => [
        'host'    => env('DB_MASTER_HOST', 'localhost'),
        'dbname'  => env('DB_MASTER_NAME', 'app_master'),
        'user'    => env('DB_MASTER_USER', 'root'),
        'pass'    => env('DB_MASTER_PASS', ''),
        'charset' => 'utf8mb4'
    ],
    'host'    => env('DB_HOST', 'localhost'),
    'dbname'  => env('DB_NAME', 'app'),
    'user'    => env('DB_USER', 'root'),
    'pass'    => env('DB_PASS', ''),
    'charset' => 'utf8mb4'
];
