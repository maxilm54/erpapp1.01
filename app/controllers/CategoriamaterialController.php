<?php

require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Categoriamaterial.php';

class CategoriamaterialController extends Controller
{
    private Categoriamaterial $modelo;

    public function __construct()
    {
        $this->modelo = new Categoriamaterial();
    }

    public function index(): void
    {
        $this->view('categoriamaterial/index', [
            'title' => 'Categorías de Materia Prima',
            'items' => $this->modelo->all(),
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . '/categoriamaterial/create');
                exit;
            }
            $this->modelo->create($_POST);
            header('Location: ' . BASE_URL . '/categoriamaterial');
            exit;
        }
        $this->view('categoriamaterial/form', [
            'title' => 'Nueva Categoría',
        ]);
    }

    public function edit(int $id): void
    {
        validarId($id, BASE_URL . '/categoriamaterial');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido.';
                header('Location: ' . BASE_URL . '/categoriamaterial/edit/' . $id);
                exit;
            }
            $this->modelo->update($id, $_POST);
            header('Location: ' . BASE_URL . '/categoriamaterial');
            exit;
        }
        $this->view('categoriamaterial/form', [
            'title'    => 'Editar Categoría',
            'registro' => $this->modelo->find($id),
        ]);
    }

    public function delete(int $id): void
    {
        validarId($id, BASE_URL . '/categoriamaterial');
        $this->modelo->delete($id);
        header('Location: ' . BASE_URL . '/categoriamaterial');
        exit;
    }

    public function activar(int $id): void
    {
        validarId($id, BASE_URL . '/categoriamaterial');
        $this->modelo->activar($id);
        header('Location: ' . BASE_URL . '/categoriamaterial');
        exit;
    }
}
