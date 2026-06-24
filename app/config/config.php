<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
//define('BASE_PATH', dirname(__DIR__,2));
require_once BASE_PATH . '/app/config/env.php';
define('APP_NAME','app');
//define('BASE_URL','http://localhost/app/public');
error_reporting(E_ALL);
ini_set('display_errors', (defined('APP_DEBUG') && APP_DEBUG) ? '1' : '0');
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', env('SMTP_PORT'));
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_SECURE', env('SMTP_SECURE'));
define('SMTP_FROM', env('SMTP_FROM'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME'));
define('FECHA_ACTUAL', date("Y-m-d H:i:s"));
function config(string $key)
{
    static $config;

    if (!$config) {
        $config = [
            'empresa' => [
                'nombre' => 'Alimentos Triba S.R.L.',
                'email' => 'contacto@alimentostriba.com.ar',
                'telefono' => '+54 9 3472 623187',
                'direccion' => 'Donato Garetto 826, Leones, Córdoba',
                'cuit' => '30-31778732-5',
                'logo' => BASE_URL . '/uploads/img_config/triba_log.png'
            ]
        ];
    }

    return $config[$key] ?? null;
}