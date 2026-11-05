<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Cheque.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';

/**
 * Cartera de cheques de terceros: listado, detalle, edición y estados.
 */
class ChequesController extends Controller
{
    private Cheque $chequeModel;

    public function __construct()
    {
        $this->chequeModel = new Cheque();
    }

    public function index(): void
    {
        $filtros = [
            'estado'      => strtoupper(trim($_GET['estado'] ?? '')),
            'vencimiento' => trim($_GET['vencimiento'] ?? ''),
            'cliente_id'  => (int)($_GET['cliente_id'] ?? 0),
            'texto'       => trim($_GET['texto'] ?? ''),
            'desde'       => trim($_GET['desde'] ?? ''),
            'hasta'       => trim($_GET['hasta'] ?? ''),
        ];

        if ($filtros['estado'] !== '' && !isset(Cheque::ESTADOS[$filtros['estado']])) {
            $filtros['estado'] = '';
        }

        $this->view('cheques/index', [
            'cheques' => $this->chequeModel->all($filtros),
            'resumen' => $this->chequeModel->resumen(),
            'filtros' => $filtros,
            'estados' => Cheque::ESTADOS,
        ]);
    }

    public function show(int $id): void
    {
        $cheque = $this->chequeModel->find($id);
        if (!$cheque) {
            $_SESSION['error'] = 'Cheque no encontrado.';
            header('Location: ' . BASE_URL . '/cheques');
            exit;
        }

        $this->view('cheques/show', [
            'cheque'  => $cheque,
            'estados' => Cheque::ESTADOS,
            'tipos'   => Cheque::TIPOS,
        ]);
    }

    public function edit(int $id): void
    {
        $cheque = $this->chequeModel->find($id);
        if (!$cheque) {
            $_SESSION['error'] = 'Cheque no encontrado.';
            header('Location: ' . BASE_URL . '/cheques');
            exit;
        }

        $this->view('cheques/form', [
            'cheque'  => $cheque,
            'estados' => Cheque::ESTADOS,
            'tipos'   => Cheque::TIPOS,
            'cajas'   => (new CajaBanco())->getActivas(),
        ]);
    }

    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/cheques/show/' . $id);
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/cheques/edit/' . $id);
            exit;
        }

        try {
            $this->chequeModel->actualizar($id, [
                'tipo'              => $_POST['cheque_tipo'] ?? 'FISICO',
                'numero'            => $_POST['cheque_numero'] ?? '',
                'banco'             => $_POST['cheque_banco'] ?? '',
                'sucursal'          => $_POST['cheque_sucursal'] ?? '',
                'titular'           => $_POST['cheque_titular'] ?? '',
                'cuit'              => $_POST['cheque_cuit'] ?? '',
                'fecha_emision'     => $_POST['cheque_fecha_emision'] ?? '',
                'fecha_vencimiento' => $_POST['cheque_fecha_vencimiento'] ?? '',
                'monto'             => $_POST['cheque_monto'] ?? 0,
                'observaciones'     => $_POST['cheque_observaciones'] ?? '',
                'caja_banco_id'     => $_POST['caja_banco_id'] ?? null,
            ]);
            $_SESSION['success'] = 'Datos del cheque actualizados.';
            header('Location: ' . BASE_URL . '/cheques/show/' . $id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/cheques/edit/' . $id);
            exit;
        }
    }

    public function cambiarEstado(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/cheques/show/' . $id);
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/cheques/show/' . $id);
            exit;
        }

        $estado = strtoupper(trim($_POST['estado'] ?? ''));

        try {
            $cheque = $this->chequeModel->find($id);
            if (!$cheque) {
                throw new Exception('Cheque no encontrado.');
            }
            if (!empty($cheque['pago_anulado']) && $estado !== 'ANULADO') {
                throw new Exception('No se puede cambiar el estado: el cobro asociado está anulado.');
            }

            $this->chequeModel->cambiarEstado($id, $estado);
            $_SESSION['success'] = 'Estado del cheque actualizado a "' . (Cheque::ESTADOS[$estado] ?? $estado) . '".';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/cheques/show/' . $id);
        exit;
    }
}
