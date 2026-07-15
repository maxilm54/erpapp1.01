<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Impuesto.php';
require_once BASE_PATH.'/app/core/Csrf.php';

class ImpuestosController extends Controller{

    private Impuesto $model;

    public function __construct(){
        $this->model = new Impuesto();
    }

    /**
     * Listado de impuestos.
     */
    public function index(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $impuestos = $this->model->getAll();

        $this->view('impuestos/index', [
            'title'     => 'Impuestos (IVA)',
            'impuestos' => $impuestos,
            'csrf'      => Csrf::generate(),
        ]);
    }

    /**
     * Guardar impuesto (crear o editar).
     */
    public function save(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        $id       = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $nombre   = trim($_POST['nombre'] ?? '');
        $codigo   = strtoupper(trim($_POST['codigo'] ?? ''));
        $porcentaje = (float)($_POST['porcentaje'] ?? 0);

        if (empty($nombre) || empty($codigo)) {
            $_SESSION['error'] = 'Nombre y código son obligatorios.';
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        $data = [
            'nombre'     => $nombre,
            'codigo'     => $codigo,
            'porcentaje' => $porcentaje,
            'activo'     => 1,
        ];

        if ($id) {
            $this->model->update($id, $data);
            $_SESSION['success'] = "Impuesto #{$id} actualizado.";
        } else {
            $this->model->create($data);
            $_SESSION['success'] = "Impuesto creado.";
        }

        header('Location: ' . BASE_URL . '/contabilidad/impuestos');
        exit;
    }

    /**
     * Activar/desactivar impuesto.
     */
    public function toggle(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $this->model->toggle($id);
        $_SESSION['success'] = "Impuesto #{$id} actualizado.";
        header('Location: ' . BASE_URL . '/contabilidad/impuestos');
        exit;
    }
}
