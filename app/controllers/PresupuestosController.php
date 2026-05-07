<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Producto.php';

class PresupuestosController extends Controller
{
    private Presupuesto $pr;

    public function __construct()
    {
        $this->pr = new Presupuesto();
    }

    public function index()
    {
        $this->view('presupuestos/index', [
            'presupuestos' => $this->pr->all()
        ]);
    }

    public function create()
    {
        $this->view('presupuestos/form', [
            'clientes'  => (new Cliente())->allactive(),
            'productos' => (new Producto())->all()
        ]);
    }

    public function store()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token PresupuestosController::store'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/presupuestos/create');
                exit;
            }

            $id = $this->pr->create([
                'cliente_id' =>(int) $_POST['cliente_id'],
                'usuario_id' => $_SESSION['user_id'],
                'items'      => is_array($_POST['items']) ? $_POST['items'] : []
            ]);
        }

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function edit($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || $pr['estado'] !== 'BORRADOR') {
            die('Presupuesto no editable');
        }

        $this->view('presupuestos/form', [
            'presupuesto' => $pr,
            'clientes'    => (new Cliente())->all(),
            'productos'   => (new Producto())->all()
        ]);
    }

    public function update($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token PresupuestosController::update'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/presupuestos/update/' . $id);
                exit;
            }

            $this->pr->update($id, [
                'cliente_id' => $_POST['cliente_id'],
                'items'      => $_POST['items']
            ]);
        }

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function show($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);
        if (!$pr) die('Presupuesto no encontrado');

        $this->view('presupuestos/show', [
            'presupuesto' => $pr
        ]);
    }

    public function aprobar($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $this->pr->aprobar($id);
        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function porCliente($clienteId)
    {
        validarId($clienteId, BASE_URL . '/presupuestos');
        $model = new Presupuesto();
        echo json_encode($model->getByCliente($clienteId));
    }

    public function showAjax($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        header('Content-Type: application/json');

        $presupuesto = $this->pr->findWithDetalle((int)$id);

        if (!$presupuesto) {
            http_response_code(404);
            echo json_encode(['error' => 'Presupuesto no encontrado']);
            return;
        }

        echo json_encode($presupuesto);
    }

    public function volvernp($presupuestoId)
    {
        validarId($presupuestoId, BASE_URL . '/presupuestos');
        $np = $this->pr->getNotaPedidoByPresupuesto($presupuestoId);
        if (!$np){
            $_SESSION['error'] = 'No existe una Nota de Pedido asociada a este Presupuesto';
            header('Location: ' . BASE_URL. '/presupuestos/show/' . $presupuestoId);
            exit;
        } 
        header('Location: ' . BASE_URL. '/notaspedido/show/' . $np);
        exit;
    }
}