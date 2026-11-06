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
            'cheque'      => $cheque,
            'estados'     => Cheque::ESTADOS,
            'tipos'       => Cheque::TIPOS,
            'cajas'       => (new CajaBanco())->getActivas(),
            'proveedores' => $this->chequeModel->proveedoresActivos(),
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
        if (($cheque['estado'] ?? '') === 'ANULADO' || !empty($cheque['pago_anulado'])) {
            $_SESSION['error'] = 'No se puede editar un cheque anulado.';
            header('Location: ' . BASE_URL . '/cheques/show/' . $id);
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
            if (!isset(Cheque::ESTADOS[$estado])) {
                throw new Exception('Estado de cheque inválido.');
            }

            $cheque = $this->chequeModel->find($id);
            if (!$cheque) {
                throw new Exception('Cheque no encontrado.');
            }

            $contexto = [
                'usuario_id'         => (int)$_SESSION['user_id'],
                'caja_banco_id'      => !empty($_POST['caja_banco_id']) ? (int)$_POST['caja_banco_id'] : null,
                'fecha_acreditacion' => trim($_POST['fecha_acreditacion'] ?? ''),
                'proveedor_id'       => !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null,
                'fecha_endoso'       => trim($_POST['fecha_endoso'] ?? ''),
            ];

            $this->chequeModel->cambiarEstado($id, $estado, $contexto);

            if ($estado === 'COBRADO') {
                $_SESSION['success'] = 'Cheque acreditado en la caja/banco seleccionada.';
            } elseif ($estado === 'ENDOSADO') {
                $_SESSION['success'] = 'Cheque endosado al proveedor y deuda cancelada.';
            } elseif ($estado === 'RECHAZADO') {
                $_SESSION['success'] = 'Cheque rechazado. El importe volvió a la cuenta corriente del cliente.';
            } elseif ($estado === 'EN_CARTERA') {
                $_SESSION['success'] = 'Cheque devuelto a cartera y efectos financieros revertidos.';
            } else {
                $_SESSION['success'] = 'Estado del cheque actualizado a "' . (Cheque::ESTADOS[$estado] ?? $estado) . '".';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/cheques/show/' . $id);
        exit;
    }
}
