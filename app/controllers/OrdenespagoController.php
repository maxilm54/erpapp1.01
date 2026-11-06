<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Csrf.php';
require_once BASE_PATH . '/app/models/Ordenpago.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';

/**
 * Ordenes de pago a proveedores: alta desde una factura, aprobación,
 * pago (caja/asiento) y anulación con reversión.
 */
class OrdenesPagoController extends Controller
{
    private OrdenPago $ordenModel;
    private CajaBanco $cajaModel;

    public function __construct()
    {
        Auth::requireLogin();
        Auth::requireTenant();
        $this->ordenModel = new OrdenPago();
        $this->cajaModel = new CajaBanco();
    }

    public function index(): void
    {
        $filtros = [
            'estado'      => strtoupper(trim($_GET['estado'] ?? '')),
            'proveedor_id'=> (int)($_GET['proveedor_id'] ?? 0),
            'texto'       => trim($_GET['texto'] ?? ''),
            'desde'       => trim($_GET['desde'] ?? ''),
            'hasta'       => trim($_GET['hasta'] ?? ''),
        ];

        if ($filtros['estado'] !== '' && !isset(OrdenPago::ESTADOS[$filtros['estado']])) {
            $filtros['estado'] = '';
        }

        $this->view('ordenes_pago/index', [
            'title'   => 'Órdenes de Pago',
            'ordenes' => $this->ordenModel->all($filtros),
            'filtros' => $filtros,
            'estados' => OrdenPago::ESTADOS,
            'resumen' => $this->ordenModel->resumen(),
        ]);
    }

    public function create(): void
    {
        $facturaId = (int)($_GET['factura'] ?? 0);
        $factura = null;
        if ($facturaId > 0) {
            require_once BASE_PATH . '/app/models/Facturacompra.php';
            $factura = (new FacturaCompra())->find($facturaId);
            if (!$factura) {
                $_SESSION['error'] = 'Factura no encontrada.';
                header('Location: ' . BASE_URL . '/ordenespago');
                exit;
            }
        }

        $this->view('ordenes_pago/form', [
            'title'      => 'Nueva Orden de Pago',
            'orden'      => null,
            'factura'    => $factura,
            'facturas'   => $this->ordenModel->facturasConSaldo(),
            'csrf'       => Csrf::generate(),
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/ordenespago');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/ordenespago/create');
            exit;
        }

        try {
            $id = $this->ordenModel->crear($_POST, (int)$_SESSION['user_id']);
            $_SESSION['success'] = "Orden de pago #{$id} creada en borrador.";
            header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $volver = !empty($_POST['factura_compra_id'])
                ? BASE_URL . '/ordenespago/create?factura=' . (int)$_POST['factura_compra_id']
                : BASE_URL . '/ordenespago/create';
            header('Location: ' . $volver);
            exit;
        }
    }

    public function show(int $id): void
    {
        validarId($id, BASE_URL . '/ordenespago');

        $orden = $this->ordenModel->find($id);
        if (!$orden) {
            $_SESSION['error'] = 'Orden de pago no encontrada.';
            header('Location: ' . BASE_URL . '/ordenespago');
            exit;
        }

        $this->view('ordenes_pago/show', [
            'title'  => "Orden de Pago #{$orden['numero']}",
            'orden'  => $orden,
            'cajas'  => $this->cajaModel->getActivas(),
            'csrf'   => Csrf::generate(),
        ]);
    }

    public function aprobar(int $id): void
    {
        $this->cambiarEstado($id, 'APROBADA', 'Orden de pago aprobada.');
    }

    public function pagar(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
            exit;
        }

        try {
            $this->ordenModel->cambiarEstado($id, 'PAGADA', [
                'usuario_id'  => (int)$_SESSION['user_id'],
                'caja_banco_id' => !empty($_POST['caja_banco_id']) ? (int)$_POST['caja_banco_id'] : null,
                'fecha_pago'  => trim($_POST['fecha_pago'] ?? ''),
            ]);
            $_SESSION['success'] = "Orden de pago #{$id} pagada. Se generó el egreso en caja/banco y el asiento contable.";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
        exit;
    }

    public function anular(int $id): void
    {
        $this->cambiarEstado($id, 'ANULADA', 'Orden de pago anulada.');
    }

    private function cambiarEstado(int $id, string $estado, string $mensaje): void
    {
        validarId($id, BASE_URL . '/ordenespago');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
            exit;
        }

        try {
            $this->ordenModel->cambiarEstado($id, $estado, [
                'usuario_id' => (int)$_SESSION['user_id'],
            ]);
            $_SESSION['success'] = $mensaje;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/ordenespago/show/' . $id);
        exit;
    }
}
