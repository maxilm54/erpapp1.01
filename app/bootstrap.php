<?php
declare(strict_types=1);

session_start();
$develop = 1;
/**
 * Constantes base
 */
define('BASE_PATH', dirname(__DIR__));
if($develop === 1){
    define('BASE_URL', 'http://localhost/app/public');//define('BASE_URL', 'https://interventral-inversely-santa.ngrok-free.dev/app/public');
}else{
    define('BASE_URL', 'https://syspre.alimentostriba.com.ar');
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