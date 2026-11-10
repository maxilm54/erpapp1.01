<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Role.php';
require_once BASE_PATH . '/app/models/User.php';
require_once BASE_PATH . '/app/models/Notificacion.php';

/**
 * "Mi Perfil": el usuario logueado ve y edita sus propios datos.
 * NO permite cambiar el rol (eso es tarea de un admin).
 * Tambien expone el centro de notificaciones por usuario (listado en la vista,
 * badge del header y modal al iniciar sesion consumen los endpoints JSON).
 */
class PerfilController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function index(): void
    {
        $userId = $_SESSION['user_id'] ?? 0;
        $user = $this->userModel->findById((int)$userId);

        if (!$user) {
            $_SESSION['error'] = 'No se encontro el usuario de la sesion.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';

            if ($nombre === '' || $email === '') {
                $_SESSION['error'] = 'Nombre y email son obligatorios.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'El email no es valido.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            // El email es unico en la BD master
            if ($this->userModel->emailExists($email, (int)$userId)) {
                $_SESSION['error'] = 'Ese email ya esta registrado por otro usuario.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            if ($password !== '' && strlen($password) < 6) {
                $_SESSION['error'] = 'La contrasena debe tener al menos 6 caracteres.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            if ($password !== '' && $password !== $confirm) {
                $_SESSION['error'] = 'Las contrasenas no coinciden.';
                header('Location: ' . BASE_URL . '/perfil');
                exit;
            }

            $this->userModel->updateUser((int)$userId, [
                'nombre'   => $nombre,
                'email'    => $email,
                'rol'      => $user['rol'],
                'password' => $password,
            ]);

            // Propagar el cambio a la copia del usuario en cada empresa (FK + nombre visible)
            foreach ($this->userModel->getTenantsForUser((int)$userId) as $t) {
                $this->userModel->syncToTenant((int)$userId, $t['dbname'], $t['host'] ?: 'localhost');
            }

            $_SESSION['success'] = 'Perfil actualizado correctamente.';
            header('Location: ' . BASE_URL . '/perfil');
            exit;
        }

        // Centro de notificaciones del usuario (alertas operativas + novedades)
        $notifModel = new Notificacion();
        $tenantId = Auth::hasTenant() ? (int)Auth::getTenantId() : null;
        try {
            if ($tenantId !== null) {
                $notifModel->sincronizarOperacionales($tenantId);
            }
        } catch (Throwable $e) {
            error_log('[Notificaciones] Error sincronizando alertas operativas: ' . $e->getMessage());
        }

        $notificaciones = $notifModel->listar((int)$userId, $tenantId);

        $this->view('perfil/index', [
            'title' => 'Mi Perfil',
            'user'  => $user,
            'notificaciones' => $notificaciones,
            'notificacionesSinLeer' => count(array_filter($notificaciones, fn($n) => $n['leido_id'] === null)),
        ]);
    }

    // =====================================================
    // Centro de notificaciones (endpoints JSON para el badge y el modal)
    // =====================================================

    /**
     * Estado de notificaciones del usuario logueado: sin_leer, items y si hay
     * que mostrar el modal de inicio de sesion (flag que se setea en el login
     * y se consume aca, una vez por sesion).
     */
    public function notificacionesJson(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sesion expirada']);
            return;
        }

        header('Content-Type: application/json');

        $notifModel = new Notificacion();
        $tenantId = Auth::hasTenant() ? (int)Auth::getTenantId() : null;

        try {
            if ($tenantId !== null) {
                $notifModel->sincronizarOperacionales($tenantId);
            }
        } catch (Throwable $e) {
            error_log('[Notificaciones] Error sincronizando alertas operativas: ' . $e->getMessage());
        }

        // El modal solo se auto-muestra una vez por inicio de sesion
        $mostrarModal = !empty($_SESSION['ver_notificaciones']);
        unset($_SESSION['ver_notificaciones']);

        $sinLeer = $notifModel->listar((int)$_SESSION['user_id'], $tenantId, true);

        echo json_encode([
            'mostrar_modal' => $mostrarModal,
            'sin_leer'      => count($sinLeer),
            'items'         => $sinLeer,
            'base_url'      => BASE_URL,
        ]);
    }

    /** Marca una notificacion como leida para el usuario logueado. */
    public function notificacionLeer($id): void
    {
        $this->responderLectura(function (Notificacion $m, int $userId, ?int $tenantId) use ($id) {
            return $m->marcarLeida((int)$id, $userId, $tenantId);
        });
    }

    /** Marca todas las notificaciones visibles como leidas para el usuario logueado. */
    public function notificacionesLeerTodas(): void
    {
        $this->responderLectura(fn(Notificacion $m, int $userId, ?int $tenantId) => (bool)$m->marcarTodas($userId, $tenantId));
    }

    /**
     * Valida POST + CSRF, ejecuta la operacion de lectura y responde JSON.
     */
    private function responderLectura(callable $accion): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Sesion expirada']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido']);
            return;
        }

        $notifModel = new Notificacion();
        $tenantId = Auth::hasTenant() ? (int)Auth::getTenantId() : null;

        try {
            $ok = $accion($notifModel, (int)$_SESSION['user_id'], $tenantId);
            echo json_encode([
                'ok'      => $ok,
                'sin_leer' => $notifModel->contarNoLeidas((int)$_SESSION['user_id'], $tenantId),
            ]);
        } catch (Throwable $e) {
            error_log('[Notificaciones] Error marcando lectura: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar la notificacion']);
        }
    }
}
