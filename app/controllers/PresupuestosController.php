<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Producto.php';
require_once BASE_PATH.'/app/services/MailService.php';

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

            $items = $_POST['items'] ?? [];
            if (empty($items)) {
                $_SESSION['error'] = 'Debe agregar al menos un producto al presupuesto.';
                header('Location: ' . BASE_URL . '/presupuestos/create');
                exit;
            }

            $id = $this->pr->create([
                'cliente_id'   => (int) $_POST['cliente_id'],
                'usuario_id'   => $_SESSION['user_id'],
                'items'        => $items,
                'observaciones'=> $_POST['observaciones'] ?? ''
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

            $items = $_POST['items'] ?? [];
            if (empty($items)) {
                $_SESSION['error'] = 'Debe agregar al menos un producto al presupuesto.';
                header('Location: ' . BASE_URL . '/presupuestos/update/' . $id);
                exit;
            }

            $this->pr->update($id, [
                'cliente_id'   => $_POST['cliente_id'],
                'items'        => $items,
                'observaciones'=> $_POST['observaciones'] ?? ''
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

    public function pdf($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || empty($pr['pdf_path']) || !file_exists($pr['pdf_path'])) {
            $_SESSION['error'] = 'PDF no disponible. Regenerelo desde la vista del presupuesto.';
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($pr['pdf_path']) . '"');
        readfile($pr['pdf_path']);
        exit;
    }

    public function regenerarPdf($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        try {
            $this->pr->generarYGuardarPdf((int)$id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
        exit;
    }

    public function reenviar($id)
    {
        validarId($id, BASE_URL . '/presupuestos');
        $pr = $this->pr->find($id);

        if (!$pr || empty($pr['pdf_path']) || !file_exists($pr['pdf_path'])) {
            $_SESSION['error'] = 'Presupuesto inválido o sin PDF. Regenerelo primero.';
            header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
            exit;
        }

        try {
            $mail = new MailService();
            $mail->enviarPresupuesto($pr, (int)$pr['cliente_id'], $_SESSION['user_id']);
            $_SESSION['success'] = 'Presupuesto enviado por email correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al enviar email: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/presupuestos/show/' . $id);
        exit;
    }
}