<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Csrf.php';
require_once BASE_PATH . '/app/models/Facturacompra.php';
require_once BASE_PATH . '/app/models/Ordenpago.php';

/**
 * Facturas de compra de proveedores: registro contra OC, listado y anulación.
 * El pago se hace por ordenes de pago, no desde acá.
 */
class FacturasCompraController extends Controller
{
    private FacturaCompra $facturaModel;

    public function __construct()
    {
        Auth::requireLogin();
        Auth::requireTenant();
        $this->facturaModel = new FacturaCompra();
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

        if ($filtros['estado'] !== '' && !isset(FacturaCompra::ESTADOS[$filtros['estado']])) {
            $filtros['estado'] = '';
        }

        $this->view('facturas_compra/index', [
            'title'      => 'Facturas de Compra',
            'facturas'   => $this->facturaModel->all($filtros),
            'filtros'    => $filtros,
            'estados'    => FacturaCompra::ESTADOS,
            'proveedores'=> $this->facturaModel->proveedoresActivos(),
        ]);
    }

    public function create(): void
    {
        $ocId = (int)($_GET['oc'] ?? 0);
        $oc = null;
        if ($ocId > 0) {
            require_once BASE_PATH . '/app/models/Ordencompra.php';
            $ocModel = new OrdenCompra();
            $oc = $ocModel->findWithDetalle($ocId);
            if ($oc && !in_array($oc['estado'], ['APROBADA', 'PARCIAL', 'RECIBIDA'], true)) {
                $_SESSION['error'] = 'Solo se pueden facturar órdenes aprobadas o recibidas.';
                header('Location: ' . BASE_URL . '/ordenescompra/show/' . $ocId);
                exit;
            }
        }

        $this->view('facturas_compra/form', [
            'title'      => 'Nueva Factura de Compra',
            'factura'    => null,
            'oc'         => $oc,
            'proveedores'=> $this->facturaModel->proveedoresActivos(),
            'ocs'        => $this->facturaModel->ocsDisponibles(),
            'monedas'    => $this->facturaModel->monedas(),
            'monedaDefault' => $this->facturaModel->monedaPorDefecto(),
            'csrf'       => Csrf::generate(),
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/facturascompra');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/facturascompra/create');
            exit;
        }

        try {
            $id = $this->facturaModel->registrar($_POST, (int)$_SESSION['user_id']);
            $_SESSION['success'] = "Factura #{$id} registrada. Ya puede generarle una orden de pago.";
            header('Location: ' . BASE_URL . '/facturascompra/show/' . $id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $volver = !empty($_POST['orden_compra_id'])
                ? BASE_URL . '/facturascompra/create?oc=' . (int)$_POST['orden_compra_id']
                : BASE_URL . '/facturascompra/create';
            header('Location: ' . $volver);
            exit;
        }
    }

    public function show(int $id): void
    {
        validarId($id, BASE_URL . '/facturascompra');

        $factura = $this->facturaModel->find($id);
        if (!$factura) {
            $_SESSION['error'] = 'Factura no encontrada.';
            header('Location: ' . BASE_URL . '/facturascompra');
            exit;
        }

        $this->view('facturas_compra/show', [
            'title'       => "Factura #{$id}",
            'factura'     => $factura,
            'ordenesPago' => $this->facturaModel->ordenesPago($id),
            'csrf'        => Csrf::generate(),
        ]);
    }

    public function anular(int $id): void
    {
        validarId($id, BASE_URL . '/facturascompra');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/facturascompra/show/' . $id);
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . BASE_URL . '/facturascompra/show/' . $id);
            exit;
        }

        try {
            $this->facturaModel->anular($id, (int)$_SESSION['user_id']);
            $_SESSION['success'] = "Factura #{$id} anulada. Asiento de compra revertido.";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/facturascompra/show/' . $id);
        exit;
    }
}
