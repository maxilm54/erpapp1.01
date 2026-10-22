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
        $error     = null;

        try {
            $respaldos = $servicio->listarRespaldos();
            $bases     = $servicio->bases();
            $destino   = $servicio->destinoBase();
        } catch (Throwable $e) {
            error_log('[Admin] No se pudo leer el directorio de respaldos: ' . $e->getMessage());
            $error = 'No se pudo acceder al directorio de respaldos: ' . $e->getMessage();
        }

        // Comprobacion del entorno (exec habilitado, mysqldump, destino escribible).
        $problemas = [];
        try {
            $problemas = $servicio->comprobarEntorno();
        } catch (Throwable $e) {
            $problemas = [$e->getMessage()];
        }

        $this->adminView('admin/backups', [
            'title'     => 'Backups',
            'respaldos' => $respaldos,
            'bases'     => $bases,
            'destino'   => $destino,
            'problemas' => $problemas,
            'error'     => $error,
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

    /**
     * Modelo de tareas (BD master), instanciado por demanda.
     */
    private function tareaModel(): Tarea{
        require_once BASE_PATH . '/app/models/Tarea.php';
        return new Tarea();
    }

    /**
     * Servicio de respaldos, instanciado por demanda.
     */
    private function backupService(): BackupService{
        require_once BASE_PATH . '/app/services/BackupService.php';
        return new BackupService();
    }
}
