<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Notapedido.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/services/MailService.php';

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
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token NotasPedidoController::store'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/notaspedido/create');
                exit;
            }
            $id = $this->np->create([
                'cliente_id'   => $_POST['cliente_id'],
                'presupuesto_id' => $_POST['presupuesto_id'] ?: null,
                'usuario_id'   => $_SESSION['user_id'],
                'items'        => $_POST['items'],
                'observaciones' => $_POST['observaciones']
            ]);
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;
        }
    }

    // 👁 Ver
    public function show($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);
        if (!$np){
            $_SESSION['error'] = 'Nota de Pedido no encontrada';
            error_log('Error: Nota de Pedido no encontrada en NotasPedidoController::show'.__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/notaspedido');
            die();
        }

        $this->view('notas_pedido/show', compact('np'));
    }

    // ✅ Aprobar
    public function approve($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $this->np->aprobar($id);
        header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
        exit;
    }

    // ❌ Anular controlar y borrar este metodo viejo
    public function anularviejo($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->np->anular($id, $_POST['motivo']);
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;
        }

        $this->view('notas_pedido/anular', ['id' => $id]);
    }
    public function anular($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    public function pdf($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);

        if (!$np || empty($np['pdf_path']) || !file_exists($np['pdf_path'])) {
            $_SESSION['error'] = 'PDF no disponible. Regenerelo desde la vista de la nota de pedido.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($np['pdf_path']) . '"');
        readfile($np['pdf_path']);
        exit;
    }

    public function regenerarPdf($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        try {
            $this->np->generarYGuardarPdf((int)$id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }

    public function reenviar($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);

        if (!$np || empty($np['pdf_path']) || !file_exists($np['pdf_path'])) {
            $_SESSION['error'] = 'Nota de Pedido inválida o sin PDF. Regenerelo primero.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }

        try {
            $mail = new MailService();
            $mail->enviarNotaPedido($np, (int)$np['cliente_id'], $_SESSION['user_id']);
            $_SESSION['success'] = 'Nota de Pedido enviada por email correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al enviar email: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }
}
