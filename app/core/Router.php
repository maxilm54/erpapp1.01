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
                // SuperAdmin va al panel admin, otros al home del tenant
                if (Auth::isSuperAdmin()) {
                    $url = 'admin/index';
                } else {
                    $url = 'home/index';
                }
            } else {
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }
        }

        $parts = explode('/', $url);
        $controllerName = ucfirst($parts[0]) . 'Controller';
        $methodRaw = $parts[1] ?? 'index';
        // Convertir guiones a camelCase: "migrations-run-all" → "migrationsRunAll"
        $method = preg_replace_callback('/-([a-z])/', function($m) { return strtoupper($m[1]); }, $methodRaw);
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

                // Sincronizar usuario al tenant si no se hizo en esta sesión
                $syncKey = 'synced_tenant_' . $tenantDb;
                if (!isset($_SESSION[$syncKey])) {
                    require_once BASE_PATH . '/app/models/User.php';
                    $syncUser = new User();
                    $syncUser->syncToTenant($_SESSION['user_id'], $tenantDb, Auth::getTenantHost());
                    $_SESSION[$syncKey] = true;
                }
            }
        }

        require_once $controllerFile;

        $controller = new $controllerName();

        if (!$isAuthRoute) {
            // Rutas del panel admin - solo superadmin
            $isAdminRoute = ($controllerName === 'AdminController' || $controllerName === 'UsersController');

            if ($isAdminRoute) {
                // Admin routes: superadmin panel
                Auth::requireAdminPanel();
            } else {
                // Todas las demas rutas requieren tenant
                // SuperAdmin NO puede acceder a modulos del tenant
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
