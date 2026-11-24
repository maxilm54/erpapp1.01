<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Tenant.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/models/Notificacion.php';
require_once BASE_PATH.'/app/models/Ctactetenant.php';
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

        // Tareas pendientes (solo si el schema master ya tiene la tabla)
        $totalTareas = 0;
        try {
            require_once BASE_PATH . '/app/models/Tarea.php';
            $totalTareas = (new Tarea())->countPendientes();
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo leer el registro de tareas: ' . $e->getMessage());
        }

        $this->adminView('admin/dashboard', [
            'title' => 'Panel de Administracion',
            'tenants' => $tenants,
            'totalTenants' => count($tenants),
            'totalUsers' => $totalUsers,
            'totalTareas' => $totalTareas
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
        $this->asegurarSchemaMaster();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            $nombre = trim($_POST['nombre'] ?? '');
            $dbname = trim($_POST['dbname'] ?? '');
            $email  = trim($_POST['email'] ?? '');

            if (empty($nombre) || empty($dbname)) {
                $_SESSION['error'] = 'Nombre y nombre de BD son obligatorios.';
                header('Location: ' . BASE_URL . '/admin/create');
                exit;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email de la empresa no es valido.';
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
                'host'   => 'localhost',
                'email'  => $email !== '' ? $email : null,
            ]);

            $tenantModel->crearDirectorios($tenantId);

            // Cuenta corriente: primer registro del mes para la empresa nueva.
            try {
                (new Ctactetenant())->asegurarRegistro($tenantId);
            } catch (Throwable $e) {
                // Si la migracion master todavia no se aplico, no se rompe el alta:
                // el registro se genera al abrir Admin -> CtaCte.
                error_log('[Admin] No se pudo crear el registro de CtaCte del tenant ' . $tenantId . ': ' . $e->getMessage());
            }

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
        $this->asegurarSchemaMaster();

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

            $email = trim($_POST['email'] ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email de la empresa no es valido.';
                header('Location: ' . BASE_URL . "/admin/edit/{$id}");
                exit;
            }

            $tenantModel->update($id, [
                'nombre'  => trim($_POST['nombre']),
                'host'    => trim($_POST['host'] ?? 'localhost'),
                'email'   => $email !== '' ? $email : null,
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
        unset($t);

        require_once BASE_PATH . '/app/helpers/MasterMigrationManager.php';
        $masterMm = new MasterMigrationManager();

        $this->adminView('admin/migrations', [
            'title'           => 'Migraciones de Base de Datos',
            'tenants'         => $tenants,
            'allMigrations'   => $allMigrations,
            'latestVersion'   => $latestVersion,
            'masterMigrations' => $masterMm->getAllMigrations(),
            'masterPending'   => $masterMm->getPending(),
            'masterApplied'   => $masterMm->getApplied(),
        ]);
    }

    /**
     * Ejecuta las migraciones pendientes del schema MASTER (no de los tenants).
     */
    public function migrationsRunMaster(): void{
        $this->requireAdmin();

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/migrations');
                exit;
            }
        }

        require_once BASE_PATH . '/app/helpers/MasterMigrationManager.php';
        $applied = (new MasterMigrationManager())->runAll();

        if (!empty($applied)) {
            $_SESSION['success'] = 'Migraciones master aplicadas: ' . implode(', ', $applied);
        } else {
            $_SESSION['info'] = 'No hay migraciones master pendientes.';
        }

        header('Location: ' . BASE_URL . '/admin/migrations');
        exit;
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

    // =====================================================
    // NOVEDADES (avisos globales del superadmin para las empresas)
    // Cada novedad tiene vigencia_hasta: fecha limite hasta la cual se muestra
    // en el centro de notificaciones de los usuarios (tenant_id NULL = todas
    // las empresas).
    // =====================================================

    /**
     * Listado + formulario de alta de novedades.
     */
    public function novedades(): void{
        $this->requireAdmin();

        $notifModel = new Notificacion();

        $this->adminView('admin/novedades', [
            'title'     => 'Novedades',
            'novedades' => $notifModel->novedades(),
            'tenants'   => Database::getMaster()
                ->query("SELECT id, nombre FROM tenants ORDER BY nombre")
                ->fetchAll(PDO::FETCH_ASSOC),
            'editar'    => null,
        ]);
    }

    /**
     * Formulario de edicion de una novedad (mismo view, form precargado).
     */
    public function novedadesEdit($id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/novedades');

        $notifModel = new Notificacion();
        $novedad = $notifModel->find((int)$id);

        if (!$novedad || $novedad['tipo'] !== Notificacion::TIPO_NOVEDAD) {
            $_SESSION['error'] = 'La novedad no existe.';
            header('Location: ' . BASE_URL . '/admin/novedades');
            exit;
        }

        $this->adminView('admin/novedades', [
            'title'     => 'Novedades',
            'novedades' => $notifModel->novedades(),
            'tenants'   => Database::getMaster()
                ->query("SELECT id, nombre FROM tenants ORDER BY nombre")
                ->fetchAll(PDO::FETCH_ASSOC),
            'editar'    => $novedad,
        ]);
    }

    /**
     * Guarda una novedad nueva.
     */
    public function novedadesCreate(): void{
        $this->requireAdmin();

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            $datos = $this->datosNovedadDesdePost();
            if ($datos === null) {
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            (new Notificacion())->crearNovedad($datos);

            $_SESSION['success'] = 'Novedad creada. Se muestra hasta el ' . date('d/m/Y', strtotime($datos['vigencia_hasta'])) . '.';
            header('Location: ' . BASE_URL . '/admin/novedades');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/novedades');
        exit;
    }

    /**
     * Guarda los cambios de una novedad existente.
     */
    public function novedadesUpdate($id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/novedades');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            $notifModel = new Notificacion();
            $novedad = $notifModel->find((int)$id);
            if (!$novedad || $novedad['tipo'] !== Notificacion::TIPO_NOVEDAD) {
                $_SESSION['error'] = 'La novedad no existe.';
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            $datos = $this->datosNovedadDesdePost();
            if ($datos === null) {
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            $notifModel->actualizarNovedad((int)$id, $datos);

            $_SESSION['success'] = 'Novedad actualizada.';
            header('Location: ' . BASE_URL . '/admin/novedades');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/novedades');
        exit;
    }

    /**
     * Baja logica de una novedad (activo = 0): deja de mostrarse en las empresas.
     */
    public function novedadesDelete($id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/novedades');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/novedades');
                exit;
            }

            (new Notificacion())->desactivar((int)$id);

            $_SESSION['success'] = 'Novedad desactivada.';
        }

        header('Location: ' . BASE_URL . '/admin/novedades');
        exit;
    }

    /**
     * Valida los campos del form de novedades. Retorna null si falta algo
     * (deja el flash de error correspondiente).
     */
    private function datosNovedadDesdePost(): ?array{
        $titulo = trim($_POST['titulo'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $vigencia = trim($_POST['vigencia_hasta'] ?? '');

        if ($titulo === '') {
            $_SESSION['error'] = 'El titulo de la novedad es obligatorio.';
            return null;
        }
        if ($vigencia === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vigencia)) {
            $_SESSION['error'] = 'La fecha de vigencia es obligatoria (hasta que dia se muestra la novedad).';
            return null;
        }

        return [
            'titulo'         => mb_substr($titulo, 0, 150),
            'mensaje'        => $mensaje !== '' ? $mensaje : null,
            'vigencia_hasta' => $vigencia,
            'tenant_id'      => $_POST['tenant_id'] ?? null,
        ];
    }

    // =====================================================
    // TAREAS (registro de tareas del panel de administracion)
    // Reemplaza al viejo tareas.json de la raiz del repo.
    // =====================================================

    /**
     * Listado de tareas con filtros (estado, empresa, texto).
     */
    public function tareas(): void{
        $this->requireAdmin();

        // El schema master se mantiene al dia solo (idempotente)
        require_once BASE_PATH . '/app/helpers/MasterMigrationManager.php';
        $masterMm = new MasterMigrationManager();
        $masterMm->ensureSchema();

        $filtros = [
            'estado'    => strtoupper(trim($_GET['estado'] ?? '')),
            'tenant_id' => $_GET['tenant_id'] ?? '',
            'q'         => trim($_GET['q'] ?? ''),
        ];

        // Si la tabla 'tareas' todavia no existe (schema master sin migrar),
        // se avisa en vez de mostrar una pantalla en blanco.
        $tareas = [];
        $pendientes = 0;
        $error = null;
        try {
            $tareas = $this->tareaModel()->all($filtros);
            $pendientes = $this->tareaModel()->countPendientes();
            $observacionesPorTarea = $this->tareaModel()->countObservaciones();
            $observaciones = [];
            foreach ($tareas as $t) {
                if (!empty($observacionesPorTarea[(int)$t['id']])) {
                    $observaciones[(int)$t['id']] = $this->tareaModel()->getObservaciones((int)$t['id']);
                }
            }
        } catch (PDOException $e) {
            error_log('[Admin] Tabla de tareas no disponible: ' . $e->getMessage());
            $error = 'La tabla de tareas todavia no existe en la BD master. '
                   . 'Ejecuta la migracion master pendiente desde Admin -> Migraciones.';
        }

        $this->adminView('admin/tareas', [
            'title'            => 'Tareas',
            'tareas'           => $tareas,
            'tenants'          => $this->tareaModel()->getTenants(),
            'filtros'          => $filtros,
            'pendientes'       => $pendientes,
            'masterPendientes' => count($masterMm->getPending()),
            'observacionesPorTarea' => $observacionesPorTarea ?? [],
            'observaciones' => $observaciones ?? [],
            'error'            => $error,
        ]);
    }

    /**
     * Crea una tarea.
     */
    public function tareasCreate(): void{
        $this->requireAdmin();

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $texto = trim($_POST['texto'] ?? '');
            if($texto === ''){
                $_SESSION['error'] = 'La descripcion de la tarea es obligatoria.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $this->tareaModel()->create([
                'texto'      => $texto,
                'tenant_id'  => $_POST['tenant_id'] ?? null,
                'usuario_id' => $_SESSION['user_id'] ?? null,
            ]);

            $_SESSION['success'] = 'Tarea creada correctamente.';
            header('Location: ' . BASE_URL . '/admin/tareas');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/tareas');
        exit;
    }

    /**
     * Confirma una tarea (la marca como HECHA).
     */
    public function tareasConfirmar(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/tareas');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $this->tareaModel()->confirmar($id, (int)($_SESSION['user_id'] ?? 0));

            $_SESSION['success'] = 'Tarea confirmada.';
            header('Location: ' . BASE_URL . '/admin/tareas');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/tareas');
        exit;
    }

    /**
     * Reabre una tarea confirmada.
     */
    public function tareasReabrir(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/tareas');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $this->tareaModel()->reabrir($id);

            $_SESSION['success'] = 'Tarea reabierta.';
            header('Location: ' . BASE_URL . '/admin/tareas');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/tareas');
        exit;
    }

    /**
     * Agrega una observacion a una tarea (con fecha y hora automaticas).
     * Permitido tanto en tareas pendientes como en tareas ya cerradas (HECHA).
     */
    public function tareasObservacion(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/tareas');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $texto = trim($_POST['observacion'] ?? '');
            if($texto === ''){
                $_SESSION['error'] = 'La observacion no puede quedar vacia.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            if($this->tareaModel()->findById($id) === null){
                $_SESSION['error'] = 'La tarea no existe.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $this->tareaModel()->addObservacion($id, (int)($_SESSION['user_id'] ?? 0), $texto);

            $_SESSION['success'] = 'Observacion agregada.';
            header('Location: ' . BASE_URL . '/admin/tareas');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/tareas');
        exit;
    }

    /**
     * Elimina una tarea.
     */
    public function tareasDelete(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/tareas');

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'] ?? '')){
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/tareas');
                exit;
            }

            $this->tareaModel()->delete($id);

            $_SESSION['success'] = 'Tarea eliminada.';
            header('Location: ' . BASE_URL . '/admin/tareas');
            exit;
        }

        header('Location: ' . BASE_URL . '/admin/tareas');
        exit;
    }

    // =====================================================
    // BACKUPS (copias de seguridad de las bases del ERP)
    // =====================================================

    /**
     * Listado de respaldos + botones para generar uno nuevo.
     */
    public function backups(): void{
        $this->requireAdmin();

        $servicio = $this->backupService();

        $respaldos = [];
        $bases     = [];
        $destino   = '';
        $problemas = [];

        // 1) Directorio de destino y listado de respaldos existentes.
        try {
            $destino   = $servicio->destinoBase();
            $respaldos = $servicio->listarRespaldos();
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo leer el directorio de respaldos: ' . $e->getMessage());
            $problemas[] = $e->getMessage();
        }

        // 2) Lista de bases a respaldar (consulta la BD master). En hosting
        //    compartido el usuario de BD a veces no tiene acceso a app_master;
        //    si falla, la pantalla sigue funcionando pero sin listar bases.
        try {
            $bases = $servicio->bases();
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo listar las bases para respaldar: ' . $e->getMessage());
            $problemas[] = 'No se pudo leer la lista de bases desde la BD master (' . $e->getMessage() . '). '
                         . 'Verificá DB_HOST, DB_USER y DB_PASS en el .env; en hosting compartido '
                         . 'puede que ese usuario no tenga acceso a app_master.';
            $bases = [];
        }

        // 3) Comprobacion del entorno (exec habilitado, mysqldump, destino
        //    escribible). No depende de la BD, así que corre independiente.
        try {
            foreach ($servicio->comprobarEntorno() as $p) {
                $problemas[] = $p;
            }
        } catch (Throwable $e) {
            $problemas[] = $e->getMessage();
        }

        $problemas = array_values(array_unique($problemas));

        $this->adminView('admin/backups', [
            'title'     => 'Backups',
            'respaldos' => $respaldos,
            'bases'     => $bases,
            'destino'   => $destino,
            'problemas' => $problemas,
            'error'     => null,
        ]);
    }

    /**
     * Genera un respaldo: master, tenants (uno por BD) o full.
     */
    public function backupsEjecutar(): void{
        $this->requireAdmin();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        if(!Csrf::validate($_POST['csrf_token'] ?? '')){
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        $modos = [
            'master'  => 'Master',
            'tenants' => 'Tenants',
            'full'    => 'Full (master + tenants)',
        ];
        $modo = $_POST['modo'] ?? '';
        if(!isset($modos[$modo])){
            $_SESSION['error'] = 'Modo de respaldo invalido.';
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        try {
            // Respaldo manual: sin rotacion, cada generacion crea su carpeta.
            $res = $this->backupService()->respaldar($modo);

            $_SESSION['success'] = sprintf(
                'Backup %s generado: %d base(s), %s MB en %s.',
                $modos[$modo],
                $res['bases'],
                number_format($res['total'] / 1048576, 2),
                $res['nombre']
            );
        } catch (Throwable $e) {
            error_log('[Admin] Fallo el respaldo: ' . $e->getMessage());
            $_SESSION['error'] = 'El respaldo fallo: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/backups');
        exit;
    }

    /**
     * Descarga un .sql del respaldo indicado.
     * El nombre del archivo viene por URL, asi que el servicio lo valida
     * (regex + realpath dentro del directorio base) antes de servirlo.
     */
    public function backupsDownload(string $dir, string $archivo): void{
        $this->requireAdmin();

        $ruta = $this->backupService()->rutaArchivo($dir, $archivo);
        if($ruta === null){
            $_SESSION['error'] = 'El archivo solicitado no existe.';
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        // En bloques: un dump grande no debe cargar entero en memoria.
        $fh = fopen($ruta, 'rb');
        while(!feof($fh)){
            echo fread($fh, 1024 * 1024);
            flush();
        }
        fclose($fh);
        exit;
    }

    /**
     * Elimina un respaldo.
     */
    public function backupsDelete(string $dir): void{
        $this->requireAdmin();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        if(!Csrf::validate($_POST['csrf_token'] ?? '')){
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/backups');
            exit;
        }

        if($this->backupService()->borrarRespaldo($dir)){
            $_SESSION['success'] = 'Respaldo eliminado.';
        } else {
            $_SESSION['error'] = 'No se pudo eliminar el respaldo (nombre invalido o inexistente).';
        }

        header('Location: ' . BASE_URL . '/admin/backups');
        exit;
    }

    // =====================================================
    // CTA CTE (cuenta corriente mensual de las empresas)
    // Un registro por empresa y por mes. conceptos: sistema, soporte y
    // desarrollo. Los pagos se reparten del vencimiento mas viejo al mas nuevo.
    // =====================================================

    /**
     * Listado principal: resumen, filtros, registros y ultimos pagos.
     */
    public function ctacte(): void{
        $this->requireAdmin();

        $error = $this->prepararCtacte();

        $filtros = [
            'tenant_id' => $_GET['tenant_id'] ?? '',
            'estado'    => strtoupper(trim($_GET['estado'] ?? '')),
            'periodo'   => trim($_GET['periodo'] ?? ''),
            'vencidos'  => !empty($_GET['vencidos']) ? 1 : '',
            'q'         => trim($_GET['q'] ?? ''),
        ];

        $registros = [];
        $pagos = [];
        $resumen = [
            'facturado'  => 0.0,
            'cobrado'    => 0.0,
            'saldo'      => 0.0,
            'vencido'    => 0.0,
            'pendientes' => 0,
        ];

        if ($error === null) {
            try {
                $model = $this->ctacteModel();

                // Genera el registro del mes actual si todavia no existe
                // (idempotente). El cron hace lo mismo desde CLI.
                $model->generarMes();

                $registros = $model->all($filtros);
                $pagos = $model->pagos(['tenant_id' => $filtros['tenant_id']]);
                $resumen = $model->resumen(!empty($filtros['tenant_id']) ? (int)$filtros['tenant_id'] : null);
            } catch (Throwable $e) {
                error_log('[Admin] Error leyendo CtaCte: ' . $e->getMessage());
                $error = 'No se pudo leer la cuenta corriente: ' . $e->getMessage();
            }
        }

        $this->adminView('admin/ctacte', [
            'title'      => 'CtaCte Empresas',
            'error'      => $error,
            'filtros'    => $filtros,
            'registros'  => $registros,
            'pagos'      => $pagos,
            'resumen'    => $resumen,
            'tenants'    => $error === null ? $this->ctacteModel()->getTenants() : [],
            'csrf'       => Csrf::generate(),
            'mesActual'  => date('Y-m'),
        ]);
    }

    /**
     * Alta manual de un registro mensual.
     */
    public function ctacteCreate(): void{
        $this->requireAdmin();

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $model = $this->ctacteModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/admin/ctacte-create');
                exit;
            }

            try {
                $id = $model->create($this->datosCtacteDesdePost(true));

                $pdfError = $this->generarComprobanteDeudaPdf($id);
                if ($pdfError !== null) {
                    $_SESSION['warning'] = $pdfError;
                }

                $_SESSION['success'] = 'Registro de cuenta corriente creado.';
                header('Location: ' . BASE_URL . '/admin/ctacte');
                exit;
            } catch (Throwable $e) {
                error_log('[Admin] Error creando registro de CtaCte: ' . $e->getMessage());
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/admin/ctacte-create');
                exit;
            }
        }

        $periodo = date('Y-m');

        $this->adminView('admin/ctacte_form', [
            'title'     => 'Nuevo registro de CtaCte',
            'registro'  => null,
            'tenants'   => $model->getTenants(),
            'preselect' => (int)($_GET['tenant_id'] ?? 0),
            'periodo'   => $periodo,
            'csrf'      => Csrf::generate(),
        ]);
    }

    /**
     * Edicion de un registro mensual (conceptos, fechas y observacion).
     */
    public function ctacteEdit(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $model = $this->ctacteModel();
        $registro = $model->findById($id);

        if (!$registro) {
            $_SESSION['error'] = 'El registro de cuenta corriente no existe.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . "/admin/ctacte-edit/{$id}");
                exit;
            }

            try {
                $model->update($id, $this->datosCtacteDesdePost(false, $registro));

                $pdfError = $this->generarComprobanteDeudaPdf($id);
                if ($pdfError !== null) {
                    $_SESSION['warning'] = $pdfError;
                }

                $_SESSION['success'] = 'Registro de cuenta corriente actualizado.';
                header('Location: ' . BASE_URL . '/admin/ctacte');
                exit;
            } catch (Throwable $e) {
                error_log('[Admin] Error actualizando registro de CtaCte: ' . $e->getMessage());
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . "/admin/ctacte-edit/{$id}");
                exit;
            }
        }

        $this->adminView('admin/ctacte_form', [
            'title'     => 'Editar registro de CtaCte',
            'registro'  => $registro,
            'tenants'   => $model->getTenants(),
            'preselect' => (int)$registro['tenant_id'],
            'periodo'   => $registro['periodo'],
            'csrf'      => Csrf::generate(),
        ]);
    }

    /**
     * Elimina un registro. Solo si no tiene pagos aplicados.
     */
    public function ctacteDelete(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $model = $this->ctacteModel();
            $registro = $model->findById($id);

            if (!$registro) {
                throw new RuntimeException('El registro de cuenta corriente no existe.');
            }
            if ((float)($registro['pago'] ?? 0) > 0) {
                throw new RuntimeException('No se puede eliminar un registro con pagos. Anula primero los pagos.');
            }

            $model->delete($id);
            try {
                $pdfPath = $this->ctactePdfService()->rutaComprobanteDeuda((int)$registro['tenant_id'], $id);
                if (is_file($pdfPath)) {
                    @unlink($pdfPath);
                }
            } catch (Throwable $e) {
                error_log('[Admin] No se pudo borrar el PDF del registro CtaCte #' . $id . ': ' . $e->getMessage());
            }

            $_SESSION['success'] = 'Registro eliminado.';
        } catch (Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/ctacte');
        exit;
    }

    /**
     * Formulario de pago: elige empresa y los registros a saldar.
     */
    public function ctactePago(): void{
        $this->requireAdmin();

        $error = $this->prepararCtacte();
        $tenantId = (int)($_GET['tenant_id'] ?? 0);
        $pendientes = [];
        $totalPendiente = 0.0;
        $tenantNombre = '';

        if ($error === null) {
            try {
                $model = $this->ctacteModel();
                $tenants = $model->getTenants(true);

                if ($tenantId > 0) {
                    $pendientes = $model->pendientes($tenantId);
                    foreach ($pendientes as $p) {
                        $totalPendiente += (float)$p['saldo_pendiente'];
                    }
                    $tenantModel = new Tenant();
                    $tenant = $tenantModel->findById($tenantId);
                    $tenantNombre = $tenant['nombre'] ?? '';
                }
            } catch (Throwable $e) {
                error_log('[Admin] Error cargando pago de CtaCte: ' . $e->getMessage());
                $error = 'No se pudo cargar la cuenta corriente: ' . $e->getMessage();
                $tenants = [];
            }
        } else {
            $tenants = [];
        }

        $this->adminView('admin/ctacte_pago', [
            'title'          => 'Registrar pago de CtaCte',
            'error'          => $error,
            'tenants'        => $tenants,
            'tenantId'       => $tenantId,
            'tenantNombre'   => $tenantNombre,
            'pendientes'     => $pendientes,
            'totalPendiente' => $totalPendiente,
            'csrf'           => Csrf::generate(),
            'hoy'            => date('Y-m-d'),
        ]);
    }

    /**
     * Registra el pago y lo reparte entre los registros elegidos.
     */
    public function ctactePagar(): void{
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte-pago');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte-pago');
            exit;
        }

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $volver = BASE_URL . '/admin/ctacte-pago' . ($tenantId > 0 ? '?tenant_id=' . $tenantId : '');

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . $volver);
            exit;
        }

        try {
            if ($tenantId <= 0) {
                throw new InvalidArgumentException('Selecciona la empresa del pago.');
            }

            $monto = $this->montoDesdePost($_POST['monto'] ?? '');
            if ($monto <= 0) {
                throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
            }

            $fecha = trim($_POST['fecha'] ?? '');
            if ($fecha === '') {
                $fecha = date('Y-m-d');
            }
            if (!$this->fechaValida($fecha)) {
                throw new InvalidArgumentException('La fecha del pago no es valida.');
            }

            $obs = trim($_POST['obs'] ?? '');
            if (mb_strlen($obs) > 500) {
                throw new InvalidArgumentException('La observacion no puede superar los 500 caracteres.');
            }

            $registros = $_POST['registros'] ?? [];
            if (!is_array($registros)) {
                $registros = [];
            }

            $resultado = $this->ctacteModel()->registrarPago(
                $tenantId,
                $monto,
                $fecha,
                $obs,
                $registros,
                (int)($_SESSION['user_id'] ?? 0)
            );

            // Comprobante de pago (letra X) y regeneracion de los comprobantes
            // de deuda de los periodos alcanzados por el pago.
            $pdfErrors = [];
            $errorPago = $this->generarComprobantePagoPdf((int)$resultado['pago_id']);
            if ($errorPago !== null) {
                $pdfErrors[] = $errorPago;
            }
            foreach ($resultado['registros'] as $registroId) {
                $errorDeuda = $this->generarComprobanteDeudaPdf((int)$registroId);
                if ($errorDeuda !== null) {
                    $pdfErrors[] = $errorDeuda;
                }
            }
            if (!empty($pdfErrors)) {
                $_SESSION['warning'] = implode(' ', array_unique($pdfErrors));
            }

            $_SESSION['success'] = sprintf(
                'Pago registrado por $%s. Se aplico a %d registro(s).',
                number_format($resultado['aplicado'], 2, ',', '.'),
                $resultado['aplicaciones']
            );
            header('Location: ' . BASE_URL . '/admin/ctacte?tenant_id=' . $tenantId);
            exit;
        } catch (Throwable $e) {
            error_log('[Admin] Error registrando pago de CtaCte: ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . $volver);
            exit;
        }
    }

    /**
     * Anula un pago y devuelve los saldos a los registros.
     */
    public function ctactePagoAnular(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $motivo = trim($_POST['motivo'] ?? '');
            if ($motivo === '') {
                throw new InvalidArgumentException('Indica el motivo de la anulacion del pago.');
            }

            $afectados = $this->ctacteModel()->anularPago(
                $id,
                $motivo,
                (int)($_SESSION['user_id'] ?? 0)
            );

            // El comprobante de pago se regenera para que quede marcado como
            // ANULADO, y se regeneran las deudas de los periodos afectados.
            $pdfErrors = [];
            $errorPago = $this->generarComprobantePagoPdf($id);
            if ($errorPago !== null) {
                $pdfErrors[] = $errorPago;
            }
            foreach ($afectados as $registroId) {
                $errorDeuda = $this->generarComprobanteDeudaPdf((int)$registroId);
                if ($errorDeuda !== null) {
                    $pdfErrors[] = $errorDeuda;
                }
            }
            if (!empty($pdfErrors)) {
                $_SESSION['warning'] = implode(' ', array_unique($pdfErrors));
            }

            $_SESSION['success'] = 'Pago anulado. Queda registrado en el historial y los saldos se devolvieron a los registros.';
        } catch (Throwable $e) {
            error_log('[Admin] Error anulando pago de CtaCte: ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/ctacte');
        exit;
    }

    /**
     * Genera (a mano) los registros de un mes para todas las empresas activas.
     */
    public function ctacteGenerar(): void{
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $periodo = trim($_POST['periodo'] ?? '');
            if ($periodo === '') {
                $periodo = date('Y-m');
            }

            $creados = $this->ctacteModel()->generarMes($periodo);

            if ($creados > 0) {
                $_SESSION['success'] = "Se crearon {$creados} registro(s) para el periodo {$periodo}.";
            } else {
                $_SESSION['info'] = "Los registros del periodo {$periodo} ya existian.";
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/ctacte');
        exit;
    }

    /**
     * Sirve (inline) el comprobante de deuda en PDF. Se regenera en cada
     * pedido para que siempre refleje el estado actual del registro.
     */
    public function ctactePdfDeuda(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $path = $this->ctactePdfService()->generarComprobanteDeuda($id);
            $this->servirPdf($path);
        } catch (Throwable $e) {
            error_log('[Admin] Error generando PDF de deuda CtaCte #' . $id . ': ' . $e->getMessage());
            $_SESSION['error'] = 'No se pudo generar el comprobante de deuda: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }
    }

    /**
     * Sirve (inline) el comprobante de pago en PDF.
     */
    public function ctactePdfPago(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $path = $this->ctactePdfService()->generarComprobantePago($id);
            $this->servirPdf($path);
        } catch (Throwable $e) {
            error_log('[Admin] Error generando PDF de pago CtaCte #' . $id . ': ' . $e->getMessage());
            $_SESSION['error'] = 'No se pudo generar el comprobante de pago: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }
    }

    /**
     * Envia el comprobante de deuda al email de la empresa.
     */
    public function ctacteEnviarDeuda(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $registro = $this->ctacteModel()->findById($id);
            if (!$registro) {
                throw new RuntimeException('El registro de cuenta corriente no existe.');
            }

            $email = trim((string)($registro['tenant_email'] ?? ''));
            $this->ctacteMailService()->enviarComprobanteDeuda(
                $id,
                $email,
                (int)($_SESSION['user_id'] ?? 0)
            );

            $_SESSION['success'] = 'Comprobante de deuda enviado a ' . $email . '.';
        } catch (Throwable $e) {
            error_log('[Admin] Error enviando comprobante de deuda CtaCte #' . $id . ': ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/ctacte');
        exit;
    }

    /**
     * Envia el comprobante de pago al email de la empresa.
     */
    public function ctacteEnviarPago(int $id): void{
        $this->requireAdmin();
        validarId($id, BASE_URL . '/admin/ctacte');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF invalido.';
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        $error = $this->prepararCtacte();
        if ($error !== null) {
            $_SESSION['error'] = $error;
            header('Location: ' . BASE_URL . '/admin/ctacte');
            exit;
        }

        try {
            $pago = $this->ctacteModel()->findPago($id);
            if (!$pago) {
                throw new RuntimeException('El pago no existe.');
            }

            $email = trim((string)($pago['tenant_email'] ?? ''));
            $this->ctacteMailService()->enviarComprobantePago(
                $id,
                $email,
                (int)($_SESSION['user_id'] ?? 0)
            );

            $_SESSION['success'] = 'Comprobante de pago enviado a ' . $email . '.';
        } catch (Throwable $e) {
            error_log('[Admin] Error enviando comprobante de pago CtaCte #' . $id . ': ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/admin/ctacte');
        exit;
    }

    /**
     * Aplica migraciones master pendientes sin bloquear el alta/edicion de
     * empresas (sirve para asegurar tenants.email).
     */
    private function asegurarSchemaMaster(): void{
        try {
            require_once BASE_PATH . '/app/helpers/MasterMigrationManager.php';
            (new MasterMigrationManager())->ensureSchema();
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo asegurar el schema master: ' . $e->getMessage());
        }
    }

    /**
     * Deja el schema master al dia y avisa si falta alguna migracion.
     */
    private function prepararCtacte(): ?string{
        try {
            require_once BASE_PATH . '/app/helpers/MasterMigrationManager.php';
            $mm = new MasterMigrationManager();
            $mm->ensureSchema();

            $pendientes = $mm->getPending();
            if (!empty($pendientes)) {
                $nombres = implode(', ', array_map(fn($m) => $m['filename'], $pendientes));
                return 'Faltan migraciones master (' . $nombres . '). '
                     . 'Ejecutalas desde Admin -> Migraciones.';
            }

            return null;
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo preparar CtaCte: ' . $e->getMessage());
            return 'No se pudo preparar el modulo CtaCte: ' . $e->getMessage()
                 . ' Ejecuta las migraciones master desde Admin -> Migraciones.';
        }
    }

    /**
     * Valida y normaliza los datos del formulario de registro mensual.
     */
    private function datosCtacteDesdePost(bool $esAlta, ?array $actual = null): array{
        $tenantId = $esAlta
            ? (int)($_POST['tenant_id'] ?? 0)
            : (int)($actual['tenant_id'] ?? 0);

        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Selecciona la empresa.');
        }
        if ($esAlta && !(new Tenant())->findById($tenantId)) {
            throw new InvalidArgumentException('La empresa seleccionada no existe.');
        }

        $periodo = $esAlta
            ? trim($_POST['periodo'] ?? '')
            : (string)($actual['periodo'] ?? '');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            throw new InvalidArgumentException('El periodo es obligatorio y debe tener el formato AAAA-MM.');
        }

        $fechaAlta = trim($_POST['fecha_alta'] ?? '');
        if ($fechaAlta === '') {
            $fechaAlta = $periodo . '-01';
        }
        if (!$this->fechaValida($fechaAlta)) {
            throw new InvalidArgumentException('La fecha de alta no es valida.');
        }

        $fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');
        if ($fechaVencimiento === '') {
            $fechaVencimiento = $periodo . '-10';
        }
        if (!$this->fechaValida($fechaVencimiento)) {
            throw new InvalidArgumentException('La fecha de vencimiento no es valida.');
        }

        $obs = trim($_POST['obs'] ?? '');
        if (mb_strlen($obs) > 500) {
            throw new InvalidArgumentException('La observacion no puede superar los 500 caracteres.');
        }

        return [
            'tenant_id'         => $tenantId,
            'periodo'           => $periodo,
            'fecha_alta'        => $fechaAlta,
            'fecha_vencimiento' => $fechaVencimiento,
            'obs'               => $obs,
            'sistema'           => $this->montoDesdePost($_POST['sistema'] ?? ''),
            'soporte'           => $this->montoDesdePost($_POST['soporte'] ?? ''),
            'desarrollo'        => $this->montoDesdePost($_POST['desarrollo'] ?? ''),
            'updated_by'        => (int)($_SESSION['user_id'] ?? 0) ?: null,
        ];
    }

    /**
     * Acepta montos con punto o coma decimal ("1234.56" o "1.234,56").
     */
    private function montoDesdePost($valor): float{
        $valor = trim((string)$valor);
        if ($valor === '') {
            return 0.0;
        }

        $valor = str_replace(' ', '', $valor);
        if (strpos($valor, ',') !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        if (!is_numeric($valor)) {
            throw new InvalidArgumentException('Hay un monto con formato invalido.');
        }

        $numero = (float)$valor;
        if ($numero < 0) {
            throw new InvalidArgumentException('Los montos no pueden ser negativos.');
        }

        return round($numero, 2);
    }

    private function fechaValida(string $fecha): bool{
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));
        return checkdate($mes, $dia, $anio);
    }

    /**
     * Modelo de tareas (BD master), instanciado por demanda.
     */
    private function tareaModel(): Tarea{
        require_once BASE_PATH . '/app/models/Tarea.php';
        return new Tarea();
    }

    /**
     * Modelo de cuenta corriente de empresas (BD master).
     */
    private function ctacteModel(): Ctactetenant{
        return new Ctactetenant();
    }

    /**
     * Servicio de comprobantes PDF de CtaCte.
     */
    private function ctactePdfService(): CtactePdfService{
        require_once BASE_PATH . '/app/services/CtactePdfService.php';
        return new CtactePdfService();
    }

    /**
     * Servicio de envio por email de comprobantes de CtaCte.
     */
    private function ctacteMailService(): CtacteMailService{
        require_once BASE_PATH . '/app/services/CtacteMailService.php';
        return new CtacteMailService();
    }

    /**
     * Genera el comprobante de deuda sin cortar la operacion principal.
     * Devuelve el mensaje de error o null si salio bien.
     */
    private function generarComprobanteDeudaPdf(int $registroId): ?string{
        try {
            $this->ctactePdfService()->generarComprobanteDeuda($registroId);
            return null;
        } catch (Throwable $e) {
            error_log('[Admin] Error generando comprobante de deuda CtaCte #' . $registroId . ': ' . $e->getMessage());
            return 'No se pudo generar el comprobante de deuda PDF: ' . $e->getMessage();
        }
    }

    /**
     * Genera el comprobante de pago sin cortar la operacion principal.
     */
    private function generarComprobantePagoPdf(int $pagoId): ?string{
        try {
            $this->ctactePdfService()->generarComprobantePago($pagoId);
            return null;
        } catch (Throwable $e) {
            error_log('[Admin] Error generando comprobante de pago CtaCte #' . $pagoId . ': ' . $e->getMessage());
            return 'No se pudo generar el comprobante de pago PDF: ' . $e->getMessage();
        }
    }

    /**
     * Envia un PDF inline al navegador (los archivos de storage no se sirven
     * por HTTP directo).
     */
    private function servirPdf(string $path): void{
        if (!is_file($path)) {
            throw new RuntimeException('El archivo PDF no existe.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    /**
     * Servicio de respaldos, instanciado por demanda.
     */
    private function backupService(): BackupService{
        require_once BASE_PATH . '/app/services/BackupService.php';
        return new BackupService();
    }
}
