<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Tenant.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/core/Csrf.php';
require_once BASE_PATH.'/app/core/Role.php';
require_once BASE_PATH.'/app/helpers/MigrationManager.php';

/**
 * Panel de Administracion - Solo SuperAdmin.
 * Gestion global de tenants, usuarios y migraciones.
 */
class AdminController extends Controller{

    private function requireAdmin(): void{
        Auth::requireAdminPanel();
    }

    /**
     * Dashboard del panel admin.
     */
    public function index(): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getAll();
        $userModel = new User();
        $totalUsers = count($userModel->getAllUsers());

        $this->adminView('admin/dashboard', [
            'title' => 'Panel de Administracion',
            'tenants' => $tenants,
            'totalTenants' => count($tenants),
            'totalUsers' => $totalUsers
        ]);
    }

    /**
     * Lista de tenants.
     */
    public function tenants(): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getAll();

        $this->adminView('admin/tenants', [
            'title' => 'Gestion de Empresas',
            'tenants' => $tenants
        ]);
    }

    /**
     * Crear nuevo tenant.
     */
    public function create(): void{
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            $nombre = trim($_POST['nombre'] ?? '');
            $dbname = trim($_POST['dbname'] ?? '');

            if (empty($nombre) || empty($dbname)) {
                $_SESSION['error'] = 'Nombre y nombre de BD son obligatorios.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
                $_SESSION['error'] = 'El nombre de la BD solo puede contener letras, numeros y guiones bajos.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            $tenantModel = new Tenant();

            $existing = $tenantModel->getActives();
            foreach ($existing as $t) {
                if ($t['dbname'] === $dbname) {
                    $_SESSION['error'] = 'Ya existe una BD con ese nombre.';
                    header('Location: ' . BASE_URL . '/admin/create');
                    exit;
                }
            }

            if (!$tenantModel->createDatabase($dbname)) {
                $_SESSION['error'] = 'Error al crear la base de datos del tenant.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            $tenantId = $tenantModel->create([
                'nombre' => $nombre,
                'dbname' => $dbname,
                'host'   => 'localhost'
            ]);

            $tenantModel->crearDirectorios($tenantId);

            $_SESSION['success'] = "Tenant '{$nombre}' creado exitosamente.";
            header('Location: ' . BASE_URL . '/admin/tenants');
            exit;
        }

        $this->adminView('admin/tenant_form', [
            'title' => 'Nueva Empresa',
            'tenant' => null,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Editar tenant existente.
     */
    public function edit(int $id): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($id);

        if (!$tenant) {
            $_SESSION['error'] = 'Tenant no encontrado.';
            header('Location: ' . BASE_URL . '/admin/tenants');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . "/admin/edit/{$id}");
                exit;
            }

            $tenantModel->update($id, [
                'nombre'  => trim($_POST['nombre']),
                'host'    => trim($_POST['host'] ?? 'localhost'),
                'activo'  => (int)($_POST['activo'] ?? 1)
            ]);

            $_SESSION['success'] = 'Tenant actualizado.';
            header('Location: ' . BASE_URL . '/admin/tenants');
            exit;
        }

        $this->adminView('admin/tenant_form', [
            'title' => 'Editar Empresa',
            'tenant' => $tenant,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Ver datos de un tenant.
     */
    public function show(int $id): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($id);

        if (!$tenant) {
            $_SESSION['error'] = 'Tenant no encontrado.';
            header('Location: ' . BASE_URL . '/admin/tenants');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($id);

        $this->adminView('admin/tenant_show', [
            'title' => 'Datos de la Empresa',
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers
        ]);
    }

    /**
     * Eliminar tenant.
     */
    public function delete(int $id): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenantModel->delete($id);

        $_SESSION['success'] = 'Tenant eliminado.';
        header('Location: ' . BASE_URL . '/admin/tenants');
        exit;
    }

    /**
     * Asignar/eliminar usuarios de un tenant.
     */
    public function users(int $tenantId): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Tenant no encontrado.';
            header('Location: ' . BASE_URL . '/admin/tenants');
            exit;
        }

        $userModel = new User();
        $tenantUsers = $tenantModel->getUsers($tenantId);
        $allUsers = $userModel->getAllUsers();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . "/admin/users/{$tenantId}");
                exit;
            }

            $action = $_POST['action'] ?? '';
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($action === 'assign' && $userId > 0) {
                $tenantModel->assignUser($tenantId, $userId);
                $userModel->syncToTenant($userId, $tenant['dbname'], $tenant['host'] ?? 'localhost');
                $_SESSION['success'] = 'Usuario asignado.';
            } elseif ($action === 'remove' && $userId > 0) {
                $tenantModel->removeUser($tenantId, $userId);
                $_SESSION['success'] = 'Usuario removido.';
            }

            header('Location: ' . BASE_URL . "/admin/users/{$tenantId}");
            exit;
        }

        $this->adminView('admin/tenant_users', [
            'title' => "Usuarios del Tenant: {$tenant['nombre']}",
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers,
            'allUsers' => $allUsers,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Lista de todos los usuarios del sistema.
     */
    public function allUsers(): void{
        $this->requireAdmin();

        $userModel = new User();
        $users = $userModel->getAllUsers();

        $this->adminView('admin/all_users', [
            'title' => 'Todos los Usuarios',
            'users' => $users
        ]);
    }

    /**
     * Crear nuevo usuario y asignarlo a un tenant.
     */
    public function createUser(): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');
            $tenantId = (int)($_POST['tenant_id'] ?? 0);

            if (empty($nombre) || empty($email) || empty($password)) {
                $_SESSION['error'] = 'Nombre, email y contrasena son obligatorios.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es valido.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            if (strlen($password) < 6) {
                $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol invalido.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            $userModel = new User();

            if ($userModel->emailExists($email)) {
                $_SESSION['error'] = 'Ya existe un usuario con ese email.';
                header('Location: ' . BASE_URL . '/admin/create-user');
                exit;
            }

            $userId = $userModel->createUser([
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            // Asignar al tenant si se selecciono uno
            if ($tenantId > 0) {
                $tenant = $tenantModel->findById($tenantId);
                if ($tenant) {
                    $tenantModel->assignUser($tenantId, $userId);
                    $userModel->syncToTenant($userId, $tenant['dbname'], $tenant['host'] ?? 'localhost');
                }
            }

            $_SESSION['success'] = "Usuario '{$nombre}' creado exitosamente.";
            header('Location: ' . BASE_URL . '/admin/all-users');
            exit;
        }

        $this->adminView('admin/user_form', [
            'title'   => 'Nuevo Usuario',
            'tenants' => $tenants,
            'user'    => null,
            'csrf'    => Csrf::generate()
        ]);
    }

    /**
     * Editar usuario existente.
     */
    public function editUser(int $userId): void{
        $this->requireAdmin();

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . BASE_URL . '/admin/all-users');
            exit;
        }

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getAll();
        $userTenants = $userModel->getTenantsForUser($userId);
        $userTenantIds = array_column($userTenants, 'id');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . "/admin/edit-user/{$userId}");
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');

            if (empty($nombre) || empty($email)) {
                $_SESSION['error'] = 'Nombre y email son obligatorios.';
                header('Location: ' . BASE_URL . "/admin/edit-user/{$userId}");
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es valido.';
                header('Location: ' . BASE_URL . "/admin/edit-user/{$userId}");
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol invalido.';
                header('Location: ' . BASE_URL . "/admin/edit-user/{$userId}");
                exit;
            }

            if (!empty($password) && strlen($password) < 6) {
                $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . "/admin/edit-user/{$userId}");
                exit;
            }

            $userModel->updateUser($userId, [
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            // Sincronizar permisos de tenant
            $selectedTenants = $_POST['tenants'] ?? [];
            foreach ($tenants as $t) {
                if (in_array($t['id'], $selectedTenants) && !in_array($t['id'], $userTenantIds)) {
                    $tenantModel->assignUser($t['id'], $userId);
                    $userModel->syncToTenant($userId, $t['dbname'], $t['host'] ?? 'localhost');
                } elseif (!in_array($t['id'], $selectedTenants) && in_array($t['id'], $userTenantIds)) {
                    $tenantModel->removeUser($t['id'], $userId);
                }
            }

            $_SESSION['success'] = 'Usuario actualizado.';
            header('Location: ' . BASE_URL . '/admin/all-users');
            exit;
        }

        $this->adminView('admin/user_form', [
            'title'        => 'Editar Usuario',
            'tenants'      => $tenants,
            'user'         => $user,
            'userTenants'  => $userTenantIds,
            'csrf'         => Csrf::generate()
        ]);
    }

    /**
     * Eliminar usuario.
     */
    public function deleteUser(int $userId): void{
        $this->requireAdmin();

        $userModel = new User();
        $userModel->deleteUser($userId);

        $_SESSION['success'] = 'Usuario eliminado.';
        header('Location: ' . BASE_URL . '/admin/all-users');
        exit;
    }

    // =====================================================
    // MIGRACIONES
    // =====================================================

    /**
     * Panel de migraciones: estado de todos los tenants.
     */
    public function migrations(): void{
        $this->requireAdmin();

        $mm = new MigrationManager();
        $allMigrations = $mm->getAllMigrations();
        $latestVersion = $mm->getLatestVersion();

        $stmt = Database::getMaster()->query("SELECT id, nombre, schema_version, activo FROM tenants ORDER BY id");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tenants as &$t) {
            $t['pending'] = $mm->getPending($t['id']);
            $t['applied'] = $mm->getApplied($t['id']);
        }

        $this->adminView('admin/migrations', [
            'title'         => 'Migraciones de Base de Datos',
            'tenants'       => $tenants,
            'allMigrations' => $allMigrations,
            'latestVersion' => $latestVersion
        ]);
    }

    /**
     * Ejecuta migraciones pendientes para un tenant especifico.
     */
    public function migrationsRun(int $tenantId): void{
        $this->requireAdmin();

        $mm = new MigrationManager();
        $applied = $mm->runForTenant($tenantId);

        if (!empty($applied)) {
            $_SESSION['success'] = 'Migraciones aplicadas: ' . implode(', ', $applied);
        } else {
            $_SESSION['info'] = 'No hay migraciones pendientes para este tenant.';
        }

        header('Location: ' . BASE_URL . '/admin/migrations');
        exit;
    }

    /**
     * Ejecuta migraciones pendientes para TODOS los tenants.
     */
    public function migrationsRunAll(): void{
        $this->requireAdmin();

        $mm = new MigrationManager();
        $results = $mm->runAll();

        $totalApplied = 0;
        foreach ($results as $r) {
            $totalApplied += count($r['applied']);
        }

        if ($totalApplied > 0) {
            $_SESSION['success'] = "Migraciones completadas. Total aplicadas: {$totalApplied}";
        } else {
            $_SESSION['info'] = 'Todos los tenants estan actualizados.';
        }

        header('Location: ' . BASE_URL . '/admin/migrations');
        exit;
    }
}
