<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/models/Tenant.php';
require_once BASE_PATH.'/app/core/Csrf.php';
require_once BASE_PATH.'/app/core/Role.php';

class UsersController extends Controller{

    private function requireAdmin(): void{
        Auth::requireLogin();
        if (!Auth::isSuperAdmin()) {
            $_SESSION['error'] = 'No tienes permisos de administrador.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    /**
     * Lista de todos los usuarios.
     */
    public function index(): void{
        $this->requireAdmin();

        $userModel = new User();
        $users = $userModel->getAllUsers();

        foreach ($users as &$u) {
            $u['tenants'] = $userModel->getTenantsForUser($u['id']);
        }

        $this->view('users/index', [
            'title' => 'Gestión de Usuarios',
            'users' => $users
        ]);
    }

    /**
     * Crear nuevo usuario.
     */
    public function create(): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getActives();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');
            $tenantIds = $_POST['tenants'] ?? [];

            if (empty($nombre) || empty($email) || empty($password)) {
                $_SESSION['error'] = 'Nombre, email y contraseña son obligatorios.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es válido.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            if (strlen($password) < 6) {
                $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol inválido.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            $userModel = new User();

            if ($userModel->emailExists($email)) {
                $_SESSION['error'] = 'Ya existe un usuario con ese email.';
                header('Location: ' . BASE_URL . '/users/create');
                exit;
            }

            $userId = $userModel->createUser([
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            foreach ($tenantIds as $tenantId) {
                $userModel->assignTenant($userId, (int)$tenantId);
            }

            $_SESSION['success'] = "Usuario '{$nombre}' creado exitosamente.";
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $this->view('users/form', [
            'title'   => 'Nuevo Usuario',
            'user'    => null,
            'tenants' => $tenants,
            'selectedTenants' => [],
            'csrf'    => Csrf::generate()
        ]);
    }

    /**
     * Editar usuario existente.
     */
    public function edit(int $id): void{
        $this->requireAdmin();

        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getActives();
        $selectedTenants = array_column($userModel->getTenantsForUser($id), 'id');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');
            $tenantIds = $_POST['tenants'] ?? [];

            if (empty($nombre) || empty($email)) {
                $_SESSION['error'] = 'Nombre y email son obligatorios.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es válido.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol inválido.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            if ($userModel->emailExists($email, $id)) {
                $_SESSION['error'] = 'Ya existe otro usuario con ese email.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            if (!empty($password) && strlen($password) < 6) {
                $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . "/users/edit/{$id}");
                exit;
            }

            $userModel->updateUser($id, [
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            $userModel->removeAllTenants($id);
            foreach ($tenantIds as $tenantId) {
                $userModel->assignTenant($id, (int)$tenantId);
            }

            $_SESSION['success'] = 'Usuario actualizado.';
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $this->view('users/form', [
            'title'   => 'Editar Usuario',
            'user'    => $user,
            'tenants' => $tenants,
            'selectedTenants' => $selectedTenants,
            'csrf'    => Csrf::generate()
        ]);
    }

    /**
     * Desactivar usuario.
     */
    public function delete(int $id): void{
        $this->requireAdmin();

        if ($id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes eliminarte a ti mismo.';
            header('Location: ' . BASE_URL . '/users');
            exit;
        }

        $userModel = new User();
        $userModel->setActive($id, false);

        $_SESSION['success'] = 'Usuario desactivado.';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }

    /**
     * Activar usuario.
     */
    public function activate(int $id): void{
        $this->requireAdmin();

        $userModel = new User();
        $userModel->setActive($id, true);

        $_SESSION['success'] = 'Usuario activado.';
        header('Location: ' . BASE_URL . '/users');
        exit;
    }
}
