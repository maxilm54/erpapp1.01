<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Ordenproduccion.php';
require_once BASE_PATH.'/app/models/Receta.php';
require_once BASE_PATH.'/app/models/Producto.php';
class OrdenproduccionController extends Controller
{
    private Ordenproduccion $model;

    public function __construct()
    {
        $this->model = new Ordenproduccion();
    }

    public function index()
    {
        $this->view('produccion/index', [
            'ordenes' => $this->model->all()
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $this->model->crear([
                    'producto_id' => $_POST['producto_id'],
                    'receta_id'   => $_POST['receta_id'],
                    'cantidad'    => $_POST['cantidad'],
                    'observaciones' => $_POST['observaciones'],
                    'usuario_id'  => $_SESSION['user_id']
                ]);

                $_SESSION['success'] = 'Orden de producción creada';
                header('Location: '.BASE_URL.'/ordenproduccion/show/'.$id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log('error al intentar crear orden de produccion'.$e->getMessage());
                header('Location: '.BASE_URL.'/ordenproduccion');
                exit;
            }
        }
        
        $this->view('produccion/create', [
            'recetas' => (new Receta())->all(),
            'productos' => (new Producto())->all()
        ]);
    }

    public function show($id)
    {
        $orden = $this->model->find((int)$id);
        if (!$orden) {
            $_SESSION['error'] = 'Orden no encontrada';
            header('Location: '.BASE_URL.'/produccion');
            exit;
        }

        $this->view('produccion/show', compact('orden'));
    }

    public function checkStock()
    {
        header('Content-Type: application/json');

        $recetaId  = (int)($_POST['receta_id'] ?? 0);
        $cantidad  = (float)($_POST['cantidad'] ?? 0);

        if ($recetaId <= 0 || $cantidad <= 0) {
            echo json_encode(['status' => 'none']);
            return;
        }

        $model = new OrdenProduccion();
        $resultado = $model->chequearStockReceta($recetaId, $cantidad);

        echo json_encode($resultado);
    }
}