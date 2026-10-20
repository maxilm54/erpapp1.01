<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Deposito.php';

class DepositosController extends Controller
{
    private Deposito $model;

    public function __construct()
    {
        $this->model = new Deposito();
    }

    public function index(): void
    {
        $this->view('depositos/index', [
            'title' => 'Depósitos',
            'depositos' => $this->model->all()
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';                header('Location: ' . BASE_URL . '/depositos/create');
                exit;
            }

            $this->model->create([
                'nombre' => trim($_POST['nombre']),
                'descripcion' => trim($_POST['descripcion'] ?? '')
            ]);

            $_SESSION['success'] = 'Depósito creado correctamente.';
            header('Location: ' . BASE_URL . '/depositos');
            exit;
        }

        $this->view('depositos/form', [
            'title' => 'Nuevo Depósito',
            'deposito' => null
        ]);
    }

    public function edit(int $id): void
    {
        validarId($id, BASE_URL . '/depositos');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . "/depositos/edit/$id");
                exit;
            }

            $this->model->update($id, [
                'nombre' => trim($_POST['nombre']),
                'descripcion' => trim($_POST['descripcion'] ?? '')
            ]);

            $_SESSION['success'] = 'Depósito actualizado correctamente.';
            header('Location: ' . BASE_URL . '/depositos');
            exit;
        }

        $this->view('depositos/form', [
            'title' => 'Editar Depósito',
            'deposito' => $this->model->find($id)
        ]);
    }

    public function delete(int $id): void
    {
        validarId($id, BASE_URL . '/depositos');
        $this->model->delete($id);
        $_SESSION['success'] = 'Depósito desactivado.';
        header('Location: ' . BASE_URL . '/depositos');
        exit;
    }
}