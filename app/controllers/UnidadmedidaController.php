<?php

require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Unidadmedida.php';

class UnidadmedidaController extends Controller
{
    private Unidadmedida $modelo;

    public function __construct()
    {
        $this->modelo = new Unidadmedida();
    }

    public function index(): void
    {
        $this->view('unidadmedida/index', [
            'title'  => 'Unidades de Medida',
            'items'  => $this->modelo->all(),
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . '/unidadmedida/create');
                exit;
            }
            $this->modelo->create($_POST);
            header('Location: ' . BASE_URL . '/unidadmedida');
            exit;
        }
        $this->view('unidadmedida/form', [
            'title' => 'Nueva Unidad de Medida',
        ]);
    }

    public function edit(int $id): void
    {
        validarId($id, BASE_URL . '/unidadmedida');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . '/unidadmedida/edit/' . $id);
                exit;
            }
            $this->modelo->update($id, $_POST);
            header('Location: ' . BASE_URL . '/unidadmedida');
            exit;
        }
        $this->view('unidadmedida/form', [
            'title'    => 'Editar Unidad de Medida',
            'registro' => $this->modelo->find($id),
        ]);
    }

    public function delete(int $id): void
    {
        validarId($id, BASE_URL . '/unidadmedida');
        $this->modelo->delete($id);
        header('Location: ' . BASE_URL . '/unidadmedida');
        exit;
    }

    public function activar(int $id): void
    {
        validarId($id, BASE_URL . '/unidadmedida');
        $this->modelo->activar($id);
        header('Location: ' . BASE_URL . '/unidadmedida');
        exit;
    }
}
