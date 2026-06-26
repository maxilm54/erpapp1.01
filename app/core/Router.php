<?php
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
            $_SESSION['error'] = 'El Menu Seleccionado No Existe.';
            error_log('El controlador no existe: ' . $controllerName);
            header('Location: ' . BASE_URL);
            exit();
        }

        // Rutas que NO requieren login
        $isAuthRoute = ($controllerName === 'AuthController');

        if (!$isAuthRoute) {
            // Todo lo demás requiere login
            Auth::requireLogin();

            // Conectar la BD del tenant ANTES de instanciar controladores
            // (porque los constructores crean modelos que llaman Database::getInstance())
            $tenantDb = Auth::getTenantDb();
            if (Auth::hasTenant() && !Database::isTenantConnectedTo($tenantDb)) {
                error_log("[Router] Conectando tenant: {$tenantDb}");
                Database::connectTenant(
                    $tenantDb,
                    Auth::getTenantHost()
                );
            }
        }

        require_once $controllerFile;

        $controller = new $controllerName();

        if (!$isAuthRoute) {
            // Solo exigir tenant para rutas que no sean admin ni users
            $isAdminRoute = ($controllerName === 'AdminController' || $controllerName === 'UsersController');
            if (!$isAdminRoute) {
                Auth::requireTenant();
            }
        }

        if (!method_exists($controller, $method)) {
            $_SESSION['error'] = 'El Menu Seleccionado No Funciona.';
            error_log('El metodo no funciona: ' . $method);
            header('Location: ' . BASE_URL);
            exit();
        }

        call_user_func_array([$controller, $method], $params);
    }
}
