<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/RemitoSalida.php';
require_once BASE_PATH.'/app/models/NotaPedido.php';
class RemitosSalidaController extends Controller
{
    private RemitoSalida $model;
    private NotaPedido $np;

    public function __construct()
    {
        $this->model = new RemitoSalida();
        $this->np = new NotaPedido();
    }
    public function index()
    {
        $remitos = $this->model->all();

        $this->view('remitos_salida/index', [
            'remitos' => $remitos
        ]);
    }
        public function create($notaPedidoId)
    {
        $np = $this->np->findWithPendientes((int)$notaPedidoId);
        error_log(print_r($np, true));

        if (!$np || $np['estado'] !== 'APROBADA') {
            $_SESSION['error'] = 'La Nota de Pedido no está aprobada o no existe.';
            error_log('Nota de Pedido inválida o no aprobada: ID '.$notaPedidoId);
            die('Nota de Pedido no válida');
        }

        if ($_POST) {
            try {
                if (empty($_POST['items'])) {
                    throw new Exception('No hay productos para remitar');
                }
                error_log('Creando Remito de Salida '.print_r($_POST['items'], true));
                $id = $this->model->create(
                    (int)$notaPedidoId,
                    (int)$_SESSION['user_id'],
                    $_POST['items'],
                    $_POST['observaciones'] ?? null
                );

                header("Location: " . BASE_URL . "/remitossalida/show/$id");
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log('Error al crear Remito de Salida: ' . $e->getMessage());
            }
        }

        $this->view('remitos_salida/form', [
            'np' => $np
        ]);
    }

    public function show($id)
    {
        $remito = $this->model->find($id);
        if (!$remito){
            $_SESSION['error'] = 'El remito no existe.';
            die('Remito no encontrado');
        } 

        $this->view('remitos_salida/show', [
            'remito' => $remito
        ]);
    }
}