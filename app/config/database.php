<?php

return [
    // Base de datos MASTER (users, tenants, user_tenant)
    'master' => [
        'host' => 'localhost',
        'dbname' => 'app_master',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    // Config por defecto (usada como fallback si no hay tenant)
    'host' => 'localhost',
    'dbname' => 'app',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
];
