<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Role.php';
require_once BASE_PATH . '/app/models/User.php';

/**
 * "Mi Perfil": el usuario logueado ve y edita sus propios datos.
 * NO permite cambiar el rol (eso es tarea de un admin).
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

        $this->view('perfil/index', [
            'title' => 'Mi Perfil',
            'user'  => $user,
        ]);
    }
}
