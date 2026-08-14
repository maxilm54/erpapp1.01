<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Tenant.php';
require_once BASE_PATH.'/app/core/Csrf.php';

/**
 * Controller para gestion de empresa por parte del tenant admin/usuario.
 * Solo usuarios dentro de un tenant pueden acceder.
 */
class EmpresaController extends Controller{

    private function requireEmpresaAdmin(): void{
        Auth::requireLogin();
        Auth::requireTenant();
    }

    /**
     * Ver datos de la empresa del usuario actual.
     */
    public function index(): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($tenantId);

        $this->view('empresa/index', [
            'title' => 'Mi Empresa',
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers
        ]);
    }

    /**
     * Editar datos de la empresa del usuario actual.
     */
    public function edit(): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $empresaConfig = $tenantModel->getEmpresaConfig($tenantId);

        $this->view('empresa/edit', [
            'title' => 'Editar Mi Empresa',
            'tenant' => $tenant,
            'empresaConfig' => $empresaConfig,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Actualizar datos de la empresa del usuario actual.
     */
    public function update(): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/empresa');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF invalido.';
            header('Location: ' . BASE_URL . '/empresa/edit');
            exit;
        }

        $data = [
            'cuit'       => trim($_POST['cuit'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'telefono'   => trim($_POST['telefono'] ?? ''),
            'direccion'  => trim($_POST['direccion'] ?? ''),
            'responsable' => trim($_POST['responsable'] ?? ''),
        ];

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = empresaUploadPath('img_config');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = 'Formato de logo no valido. Use JPG, PNG, GIF o WebP.';
                header('Location: ' . BASE_URL . '/empresa/edit');
                exit;
            }

            $filename = 'logo.' . $ext;
            $dest = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $_SESSION['error'] = 'Error al subir el logo.';
                header('Location: ' . BASE_URL . '/empresa/edit');
                exit;
            }

            $data['logo'] = $filename;
        }

        $tenantModel->update($tenantId, $data);

        // Crear directorios si no existen
        $tenantModel->crearDirectorios($tenantId);

        $_SESSION['success'] = 'Empresa actualizada correctamente.';
        header('Location: ' . BASE_URL . '/empresa');
        exit;
    }

    /**
     * Ver y gestionar usuarios de la empresa del usuario actual.
     */
    public function users(): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($tenantId);

        $this->view('empresa/users', [
            'title' => 'Usuarios de mi Empresa',
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers
        ]);
    }

    /**
     * Crear un nuevo usuario y asignarlo automaticamente a la empresa del usuario actual.
     */
    public function createUser(): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');

            if (empty($nombre) || empty($email) || empty($password)) {
                $_SESSION['error'] = 'Nombre, email y contrasena son obligatorios.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es valido.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            if (strlen($password) < 6) {
                $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol invalido.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            // No permitir asignar SUPERADMIN desde tenant
            if (!Role::canAssignInTenant($rol)) {
                $_SESSION['error'] = 'No puedes asignar el rol Super Administrador desde aqui.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            $userModel = new User();

            if ($userModel->emailExists($email)) {
                $_SESSION['error'] = 'Ya existe un usuario con ese email.';
                header('Location: ' . BASE_URL . '/empresa/create-user');
                exit;
            }

            $userId = $userModel->createUser([
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            // Asignar automaticamente a la empresa actual
            $userModel->assignTenant($userId, $tenantId);

            // Sincronizar el usuario al tenant para foreign keys
            $tenantHost = $tenant['host'] ?? 'localhost';
            $userModel->syncToTenant($userId, $tenant['dbname'], $tenantHost);

            $_SESSION['success'] = "Usuario '{$nombre}' creado y asignado a {$tenant['nombre']}.";
            header('Location: ' . BASE_URL . '/empresa/users');
            exit;
        }

        $this->view('empresa/user_form', [
            'title'   => 'Nuevo Usuario - ' . $tenant['nombre'],
            'tenant'  => $tenant,
            'user'    => null,
            'csrf'    => Csrf::generate()
        ]);
    }

    /**
     * Eliminar (desasignar) un usuario de la empresa del usuario actual.
     */
    public function removeUser(int $userId): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();

        if ($userId === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes eliminarte a ti mismo.';
            header('Location: ' . BASE_URL . '/empresa/users');
            exit;
        }

        $userModel = new User();
        $userModel->removeTenant($userId, $tenantId);

        $_SESSION['success'] = 'Usuario removido de la empresa.';
        header('Location: ' . BASE_URL . '/empresa/users');
        exit;
    }

    /**
     * Editar un usuario de la empresa del usuario actual.
     */
    public function editUser(int $userId): void{
        $this->requireEmpresaAdmin();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . BASE_URL . '/empresa/users');
            exit;
        }

        // Verificar que el usuario pertenece a esta empresa
        $userTenants = $userModel->getTenantsForUser($userId);
        $tenantIds = array_column($userTenants, 'id');
        if (!in_array($tenantId, $tenantIds)) {
            $_SESSION['error'] = 'Este usuario no pertenece a tu empresa.';
            header('Location: ' . BASE_URL . '/empresa/users');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');

            if (empty($nombre) || empty($email)) {
                $_SESSION['error'] = 'Nombre y email son obligatorios.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es valido.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol invalido.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            // No permitir asignar SUPERADMIN desde tenant
            if (!Role::canAssignInTenant($rol)) {
                $_SESSION['error'] = 'No puedes asignar el rol Super Administrador desde aqui.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            if (!empty($password) && strlen($password) < 6) {
                $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . "/empresa/edit-user/{$userId}");
                exit;
            }

            $userModel->updateUser($userId, [
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            $_SESSION['success'] = 'Usuario actualizado.';
            header('Location: ' . BASE_URL . '/empresa/users');
            exit;
        }

        $this->view('empresa/user_form', [
            'title'   => 'Editar Usuario - ' . $tenant['nombre'],
            'tenant'  => $tenant,
            'user'    => $user,
            'csrf'    => Csrf::generate()
        ]);
    }
}
