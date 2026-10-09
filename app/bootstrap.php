<?php
/*
*Estas LIsnas las agrego para mostrar cambios en mi repsitorio y luego migrarlos al host remoto
*
*Segundo INtentto de cambio en el repositorio remoto
*
*/
declare(strict_types=1);
// Configurar cookies seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.cookie_samesite', 'Strict');
//session_start();
// Configuración de cookies seguras
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variables de entorno para producción
//$develop = getenv('APP_ENV') === 'production' ? 0 : 1;
$develop = 1;
/**
 * Constantes base//
 */
define('BASE_PATH', dirname(__DIR__));
// Detección dinámica del protocolo (http o https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
// Detección dinámica del host (puede ser localhost, una IP como 192.168.1.50, o tu dominio remoto)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if($develop === 1 || $host === 'localhost' || filter_var(str_replace(':80', '', $host), FILTER_VALIDATE_IP)){
   define('BASE_URL', $protocol . '://' . $host . '/app/public');
}else{
    define('BASE_URL', 'https://erpcliente.dmtech.com.ar');
}


/**
 * Configuración
 */
require_once BASE_PATH . '/app/config/env.php';
require_once BASE_PATH . '/app/config/config.php';

/**
 * Autoload Composer
 */
require_once BASE_PATH . '/vendor/autoload.php';

/**
 * Core
 */
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/core/Auth.php';
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/core/Router.php';
require_once BASE_PATH . '/app/core/Csrf.php';
require_once BASE_PATH . '/app/helpers/validationHelper.php';