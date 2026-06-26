<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Tenant.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/core/Csrf.php';
require_once BASE_PATH.'/app/core/Role.php';
require_once BASE_PATH.'/app/helpers/MigrationManager.php';

class AdminController extends Controller{

    private function requireAdmin(): void{
        Auth::requireLogin();
        if (!Auth::isSuperAdmin()) {
            $_SESSION['error'] = 'No tienes permisos de administrador.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    /**
     * Lista de tenants.
     */
    public function index(): void{
        $this->requireAdmin();

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getAll();

        $this->view('admin/tenants', [
            'title' => 'Gestión de Empresas (Tenants)',
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
                $_SESSION['error'] = 'CSRF inválido.';
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

            // Validar nombre de BD (solo letras, números, guiones bajos)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
                $_SESSION['error'] = 'El nombre de la BD solo puede contener letras, números y guiones bajos.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            $tenantModel = new Tenant();

            // Verificar que no exista
            $existing = $tenantModel->getActives();
            foreach ($existing as $t) {
                if ($t['dbname'] === $dbname) {
                    $_SESSION['error'] = 'Ya existe una BD con ese nombre.';
                    header('Location: ' . BASE_URL . '/admin/create');
                    exit;
                }
            }

            // Crear la BD del tenant con el esquema template
            if (!$tenantModel->createDatabase($dbname)) {
                $_SESSION['error'] = 'Error al crear la base de datos del tenant.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            // Crear registro del tenant
            $tenantId = $tenantModel->create([
                'nombre' => $nombre,
                'dbname' => $dbname,
                'host'   => 'localhost'
            ]);

            $_SESSION['success'] = "Tenant '{$nombre}' creado exitosamente.";
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $this->view('admin/tenant_form', [
            'title' => 'Nueva Empresa (Tenant)',
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
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . "/admin/edit/{$id}");
                exit;
            }

            $tenantModel->update($id, [
                'nombre'  => trim($_POST['nombre']),
                'host'    => trim($_POST['host'] ?? 'localhost'),
                'activo'  => (int)($_POST['activo'] ?? 1)
            ]);

            $_SESSION['success'] = 'Tenant actualizado.';
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $this->view('admin/tenant_form', [
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
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($id);

        $this->view('admin/tenant_show', [
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
        header('Location: ' . BASE_URL . '/admin');
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
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $userModel = new User();
        $tenantUsers = $tenantModel->getUsers($tenantId);
        $allUsers = $userModel->getAllUsers();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . "/admin/users/{$tenantId}");
                exit;
            }

            $action = $_POST['action'] ?? '';
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($action === 'assign' && $userId > 0) {
                $tenantModel->assignUser($tenantId, $userId);
                $_SESSION['success'] = 'Usuario asignado.';
            } elseif ($action === 'remove' && $userId > 0) {
                $tenantModel->removeUser($tenantId, $userId);
                $_SESSION['success'] = 'Usuario removido.';
            }

            header('Location: ' . BASE_URL . "/admin/users/{$tenantId}");
            exit;
        }

        $this->view('admin/tenant_users', [
            'title' => "Usuarios del Tenant: {$tenant['nombre']}",
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers,
            'allUsers' => $allUsers,
            'csrf' => Csrf::generate()
        ]);
    }

    /**
     * Selector de tenant para superadmin (acceso rápido).
     */
    public function selectTenant(): void{
        Auth::requireLogin();
        if (!Auth::isSuperAdmin()) {
            $_SESSION['error'] = 'No tienes permisos.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $tenantModel = new Tenant();
        $tenants = $tenantModel->getActives();

        $this->view('admin/select_tenant', [
            'title' => 'Seleccionar Empresa (SuperAdmin)',
            'tenants' => $tenants
        ]);
    }

    // =====================================================
    // MÉTODOS PARA OPERARIO: Gestión de su propia empresa
    // =====================================================

    /**
     * Ver datos de la empresa del usuario actual (ADMIN o USUARIO).
     */
    public function empresa(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($tenantId);

        $this->view('admin/empresa', [
            'title' => 'Mi Empresa',
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers
        ]);
    }

    /**
     * Ver y gestionar usuarios de la empresa del usuario actual.
     */
    public function empresaUsers(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $tenantId = Auth::getTenantId();
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($tenantId);

        if (!$tenant) {
            $_SESSION['error'] = 'Empresa no encontrada.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }

        $tenantUsers = $tenantModel->getUsers($tenantId);

        $this->view('admin/empresa_users', [
            'title' => 'Usuarios de mi Empresa',
            'tenant' => $tenant,
            'tenantUsers' => $tenantUsers
        ]);
    }

    /**
     * Crear un nuevo usuario y asignarlo automáticamente a la empresa del usuario actual.
     */
    public function empresaCreateUser(): void{
        Auth::requireLogin();
        Auth::requireTenant();

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
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');

            if (empty($nombre) || empty($email) || empty($password)) {
                $_SESSION['error'] = 'Nombre, email y contraseña son obligatorios.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es válido.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            if (strlen($password) < 6) {
                $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol inválido.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            // Solo permitir crear roles OPERARIO o VISOR (no ADMIN global)
            if ($rol === 'ADMIN') {
                $_SESSION['error'] = 'No puedes asignar el rol de Administrador Global desde aquí.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            $userModel = new User();

            if ($userModel->emailExists($email)) {
                $_SESSION['error'] = 'Ya existe un usuario con ese email.';
                header('Location: ' . BASE_URL . '/admin/empresa-create-user');
                exit;
            }

            $userId = $userModel->createUser([
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            // Asignar automáticamente a la empresa actual
            $userModel->assignTenant($userId, $tenantId);

            $_SESSION['success'] = "Usuario '{$nombre}' creado y asignado a {$tenant['nombre']}.";
            header('Location: ' . BASE_URL . '/admin/empresa-users');
            exit;
        }

        $this->view('admin/empresa_user_form', [
            'title'   => 'Nuevo Usuario - ' . $tenant['nombre'],
            'tenant'  => $tenant,
            'user'    => null,
            'csrf'    => Csrf::generate()
        ]);
    }

    /**
     * Eliminar (desasignar) un usuario de la empresa del usuario actual.
     */
    public function empresaRemoveUser(int $userId): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $tenantId = Auth::getTenantId();

        if ($userId === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes eliminarte a ti mismo.';
            header('Location: ' . BASE_URL . '/admin/empresa-users');
            exit;
        }

        $userModel = new User();
        $userModel->removeTenant($userId, $tenantId);

        $_SESSION['success'] = 'Usuario removido de la empresa.';
        header('Location: ' . BASE_URL . '/admin/empresa-users');
        exit;
    }

    /**
     * Editar un usuario de la empresa del usuario actual.
     */
    public function empresaEditUser(int $userId): void{
        Auth::requireLogin();
        Auth::requireTenant();

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
            header('Location: ' . BASE_URL . '/admin/empresa-users');
            exit;
        }

        // Verificar que el usuario pertenece a esta empresa
        $userTenants = $userModel->getTenantsForUser($userId);
        $tenantIds = array_column($userTenants, 'id');
        if (!in_array($tenantId, $tenantIds)) {
            $_SESSION['error'] = 'Este usuario no pertenece a tu empresa.';
            header('Location: ' . BASE_URL . '/admin/empresa-users');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol      = strtoupper($_POST['rol'] ?? 'USUARIO');

            if (empty($nombre) || empty($email)) {
                $_SESSION['error'] = 'Nombre y email son obligatorios.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es válido.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            if (!Role::isValidRole($rol)) {
                $_SESSION['error'] = 'Rol inválido.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            // No permitir asignar rol ADMIN global
            if ($rol === 'ADMIN') {
                $_SESSION['error'] = 'No puedes asignar el rol de Administrador Global desde aquí.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            if (!empty($password) && strlen($password) < 6) {
                $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . "/admin/empresa-edit-user/{$userId}");
                exit;
            }

            $userModel->updateUser($userId, [
                'nombre'   => $nombre,
                'email'    => $email,
                'password' => $password,
                'rol'      => $rol
            ]);

            $_SESSION['success'] = 'Usuario actualizado.';
            header('Location: ' . BASE_URL . '/admin/empresa-users');
            exit;
        }

        $this->view('admin/empresa_user_form', [
            'title'   => 'Editar Usuario - ' . $tenant['nombre'],
            'tenant'  => $tenant,
            'user'    => $user,
            'csrf'    => Csrf::generate()
        ]);
    }

    // =====================================================
    // MIGRACIONES (solo superadmin)
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

        $this->view('admin/migrations', [
            'title'         => 'Migraciones de Base de Datos',
            'tenants'       => $tenants,
            'allMigrations' => $allMigrations,
            'latestVersion' => $latestVersion
        ]);
    }

    /**
     * Ejecuta migraciones pendientes para un tenant específico.
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
            $_SESSION['info'] = 'Todos los tenants están actualizados.';
        }

        header('Location: ' . BASE_URL . '/admin/migrations');
        exit;
    }
}
