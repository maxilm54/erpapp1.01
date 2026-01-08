<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/NotaPedido.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';

class NotasPedidoController extends Controller
{
    private NotaPedido $np;

    public function __construct()
    {
        $this->np = new NotaPedido();
    }

    // 📄 Listado
    public function index()
    {
        $this->view('notas_pedido/index', [
            'notas' => $this->np->all()
        ]);
    }

    // ➕ Crear
    public function create()
    {
        $this->view('notas_pedido/form', [
            'clientes'     => $this->np->clientes(),
            'presupuestos' => []
        ]);
    }

    // 💾 Guardar
    public function store()
    {
        try {
            $id = $this->np->create([
                'cliente_id'     => $_POST['cliente_id'],
                'presupuesto_id' => $_POST['presupuesto_id'] ?? NULL,
                'observaciones'  => $_POST['observaciones'] ?? NULL,
                'usuario_id'     => $_SESSION['user_id'],
                'items'          => $_POST['items'] ?? []
            ]);
            // 🔒 Si viene presupuesto → bloquearlo
            if (!empty($_POST['presupuesto_id'])) {
                (new Presupuesto())->marcarAsignado($_POST['presupuesto_id']);
            }
            
            $_SESSION['success'] = 'Nota de Pedido creada correctamente';
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log($e->getMessage());
            header('Location: '.BASE_URL.'/notaspedido');
            exit;
        }
    }

    // 👁 Ver
    public function show($id)
    {
        $np = $this->np->findWithDetalle($id);
        if (!$np) die('Nota de Pedido no encontrada');

        $this->view('notas_pedido/show', compact('np'));
    }

    // ✅ Aprobar
    public function approve($id)
    {
        $this->np->aprobar($id);
        header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
        exit;
    }

    // ❌ Anular controlar y borrar este metodo viejo
    public function anularviejo($id)
    {
        if ($_POST) {
            $this->np->anular($id, $_POST['motivo']);
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;
        }

        $this->view('notas_pedido/anular', ['id' => $id]);
    }
    public function anular($id)
    {
        if ($_POST) {
            try {
                $this->np->anular(
                    (int)$id,
                    $_POST['motivo']
                );

                $_SESSION['success'] = 'Nota de Pedido anulada';
                header('Location: ' . BASE_URL . '/notaspedido');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        $this->view('notas_pedido/anular', ['id' => $id]);
    }
}