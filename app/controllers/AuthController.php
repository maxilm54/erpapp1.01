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
                'role' => Role::OPERARIO // Asignar rol por defecto
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

            $_SESSION['user_id'] = $user['id'];
            header('Location: ' . BASE_URL);
            exit;
        }

        $this->login2('auth/login', [
            'title' => 'Login'
        ]);
    }
    public function logout(): void{
        session_destroy();
        header('Location: ' . BASE_URL);
    }
}