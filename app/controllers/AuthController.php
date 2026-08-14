<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/core/Csrf.php';
require_once BASE_PATH.'/app/helpers/MailHelper.php';
require_once BASE_PATH.'/app/core/Role.php';

class AuthController extends Controller{
    public function register(): void{
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf'])) {
                die('CSRF inválido');
            }

            $token = bin2hex(random_bytes(32));
            $userModel = new User();

            $userModel->create([
                'nombre' => $_POST['nombre'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'token' => $token,
                'role' => 'USUARIO'
            ]);

            $link = BASE_URL . "/auth/verify/$token";

            MailHelper::send(
                $_POST['email'],
                'Confirmar cuenta Triba-APP',
                "<p>Click para verificar:</p><a href='$link'>$link</a>"
            );

            echo "Revisá tu email para verificar la cuenta.";
            return;
        }

        $this->view('auth/register', [
            'title' => 'Registro',
            'csrf' => Csrf::generate()
        ]);
    }

    public function verify(string $token): void{
        $userModel = new User();
        $userModel->verifyEmail($token);
        echo "Cuenta verificada. Ya podés iniciar sesión.";
    }

    public function login(): void{
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(!Csrf::validate($_POST['csrf_token'])){
                error_log('Intento de inicio de sesion con CSRF inválido');
                $_SESSION['error'] = 'CSRF inválido. Por favor, intenta de nuevo.';
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }

            $userModel = new User();
            $user = $userModel->findByEmail($_POST['email']);

            if (!$user || !$user['email_verificado']) {
                error_log('Intento de inicio de sesion con credenciales inválidas o email no verificado: ' . $_POST['email']);
                $_SESSION['error'] = 'Credenciales inválidas o email no verificado.';
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }

            if (!password_verify($_POST['password'], $user['password_hash'])) {
                $_SESSION['error'] = 'Credenciales inválidas.';
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }

            // Login valido - establecer sesion de usuario
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_rol'] = $user['rol'];

            // SuperAdmin va directo al panel admin
            if (strtoupper($user['rol']) === 'SUPERADMIN') {
                header('Location: ' . BASE_URL . '/admin');
                exit;
            }

            // Buscar a qué tenants tiene acceso
            $tenants = $userModel->getTenantsForUser($user['id']);

            if (count($tenants) === 0) {
                // No tiene acceso a ningún tenant
                $_SESSION['error'] = 'Tu cuenta no tiene acceso a ningún módulo. Contacta al administrador.';
                header('Location: ' . BASE_URL . '/auth/login');
                exit;
            }

            if (count($tenants) === 1) {
                // Solo tiene 1 tenant → auto-conectar
                $t = $tenants[0];
                Auth::setTenant($t['id'], $t['dbname'], $t['nombre'], $t['host']);
                header('Location: ' . BASE_URL);
                exit;
            }

            // Tiene varios tenants → mostrar selector
            header('Location: ' . BASE_URL . '/auth/select-tenant');
            exit;
        }

        $this->login2('auth/login', [
            'title' => 'Login'
        ]);
    }

    /**
     * Selector de tenant — solo se muestra si el usuario tiene más de 1 tenant.
     */
    public function selectTenant(): void{
        Auth::requireLogin();

        $userModel = new User();
        $tenants = $userModel->getTenantsForUser($_SESSION['user_id']);

        // Si solo tiene 1 tenant, redirigir automáticamente
        if (count($tenants) === 1) {
            $t = $tenants[0];
            Auth::setTenant($t['id'], $t['dbname'], $t['nombre'], $t['host']);
            header('Location: ' . BASE_URL);
            exit;
        }

        // Si tiene 0 tenants, al login
        if (count($tenants) === 0) {
            $_SESSION['error'] = 'No tienes acceso a ningún módulo.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }

        // POST: seleccionar tenant
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(!Csrf::validate($_POST['csrf_token'])){
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . '/auth/select-tenant');
                exit;
            }

            $tenantId = (int)($_POST['tenant_id'] ?? 0);

            foreach ($tenants as $t) {
                if ((int)$t['id'] === $tenantId) {
                    Auth::setTenant($t['id'], $t['dbname'], $t['nombre'], $t['host']);
                    header('Location: ' . BASE_URL);
                    exit;
                }
            }

            $_SESSION['error'] = 'Tenant inválido.';
            header('Location: ' . BASE_URL . '/auth/select-tenant');
            exit;
        }

        $this->login2('auth/select-tenant', [
            'title' => 'Seleccionar Empresa',
            'tenants' => $tenants,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Cambiar de tenant (para usuarios con acceso a múltiples tenants).
     */
    public function switchTenant(int $tenantId): void{
        Auth::requireLogin();

        $userModel = new User();
        $tenants = $userModel->getTenantsForUser($_SESSION['user_id']);

        foreach ($tenants as $t) {
            if ((int)$t['id'] === $tenantId) {
                Auth::setTenant($t['id'], $t['dbname'], $t['nombre'], $t['host']);
                header('Location: ' . BASE_URL);
                exit;
            }
        }

        $_SESSION['error'] = 'No tienes acceso a ese tenant.';
        header('Location: ' . BASE_URL);
        exit;
    }

    public function logout(): void{
        Auth::clearTenant();
        session_destroy();
        header('Location: ' . BASE_URL);
    }
}
