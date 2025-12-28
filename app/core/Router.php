<?php

require_once BASE_PATH . '/app/core/Auth.php';
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Model.php';

class Router
{
    public function run(): void
    {
        $url = $_GET['url'] ?? '';
        $url = trim($url, '/');

        // Si no hay URL → decidir según sesión
        if ($url === '') {
            if (Auth::check()) {
                $url = 'home/index';
            } else {
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }
        }

        $parts = explode('/', $url);
        $controllerName = ucfirst($parts[0]) . 'Controller';
        $method = $parts[1] ?? 'index';
        $params = array_slice($parts, 2);

        $controllerFile = BASE_PATH . '/app/controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            die('Controlador no encontrado');
        }

        require_once $controllerFile;

        $controller = new $controllerName();

        // Protección: todo menos Auth requiere login
        if ($controllerName !== 'AuthController') {
            Auth::requireLogin();
        }

        if (!method_exists($controller, $method)) {
            die('Método no encontrado');
        }

        call_user_func_array([$controller, $method], $params);
    }
}