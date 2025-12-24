<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/User.php';
require_once BASE_PATH.'/app/core/Csrf.php';
require_once BASE_PATH.'/app/helpers/MailHelper.php';

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
                'token' => $token
            ]);

            $link = BASE_URL . "/auth/verify/$token";

            MailHelper::send(
                $_POST['email'],
                'Verificá tu cuenta',
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
            $userModel = new User();
            $user = $userModel->findByEmail($_POST['email']);

            if (!$user || !$user['email_verificado']) {
                die('Credenciales inválidas o email no verificado');
            }

            if (!password_verify($_POST['password'], $user['password_hash'])) {
                die('Credenciales inválidas');
            }

            $_SESSION['user_id'] = $user['id'];
            header('Location: ' . BASE_URL);
            exit;
        }

        $this->view('auth/login', [
            'title' => 'Login'
        ]);
    }
    public function logout(): void{
        session_destroy();
        header('Location: ' . BASE_URL);
    }
}