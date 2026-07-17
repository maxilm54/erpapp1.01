<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Cobro.php';
require_once BASE_PATH . '/app/models/Cliente.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/models/Cuentacorrientecliente.php';

class CobrosController extends Controller
{
    private Cobro $cobroModel;
    private Cliente $clienteModel;
    private CajaBanco $cajaModel;
    private CuentaCorrienteCliente $ccModel;

    public function __construct()
    {
        $this->cobroModel = new Cobro();
        $this->clienteModel = new Cliente();
        $this->cajaModel = new CajaBanco();
        $this->ccModel = new CuentaCorrienteCliente();
    }

    /**
     * Listado de todos los cobros.
     */
    public function index(): void
    {
        $cobros = $this->cobroModel->all();
        $this->view('cobros/index', [
            'cobros' => $cobros,
        ]);
    }

    /**
     * Detalle de un cobro.
     */
    public function show(int $id): void
    {
        $cobro = $this->cobroModel->find($id);
        if (!$cobro) {
            $_SESSION['error'] = 'Cobro no encontrado.';
            header("Location: " . BASE_URL . "/cobros");
            exit;
        }
        $this->view('cobros/show', [
            'cobro' => $cobro,
        ]);
    }

    /**
     * Formulario para crear un nuevo cobro.
     */
    public function create(): void
    {
        $clientes = $this->clienteModel->all();
        $cajas = $this->cajaModel->getActivas();
        $ocasionales = $this->ccModel->clientesOcasionalesConDeuda();
        $this->view('cobros/create', [
            'clientes' => $clientes,
            'cajas' => $cajas,
            'ocasionales' => $ocasionales,
        ]);
    }

    /**
     * AJAX: Obtener deudas de un cliente registrado.
     */
    public function deudas(int $clienteId): void
    {
        header('Content-Type: application/json');
        $deudas = $this->ccModel->deudasActualCliente($clienteId);
        echo json_encode($deudas);
    }

    /**
     * AJAX: Obtener deudas de un cliente ocasional por nombre.
     */
    public function deudasOcasional(): void
    {
        header('Content-Type: application/json');
        $nombre = trim($_GET['nombre'] ?? '');
        if (empty($nombre)) {
            echo json_encode([]);
            return;
        }
        $saldo = $this->ccModel->saldoPorNombreOcasional($nombre);
        echo json_encode([$saldo]);
    }

    /**
     * Registrar el cobro.
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . BASE_URL . "/cobros");
            exit;
        }

        try {
            $clienteId = (int)($_POST['cliente_id'] ?? 0);
            $clienteNombre = trim($_POST['cliente_nombre'] ?? '');
            $monto = (float)($_POST['monto'] ?? 0);
            $medioPago = trim($_POST['medio_pago'] ?? '');
            $cajaBancoId = !empty($_POST['caja_banco_id']) ? (int)$_POST['caja_banco_id'] : null;
            $observaciones = trim($_POST['observaciones'] ?? '');
            $remitoId = !empty($_POST['remito_id']) ? (int)$_POST['remito_id'] : null;

            if ($clienteId <= 0) throw new Exception('Debe seleccionar un cliente.');
            if ($monto <= 0) throw new Exception('El monto debe ser mayor a cero.');
            if (empty($medioPago)) throw new Exception('Debe seleccionar un medio de pago.');

            // Si es ocasional (9999), el nombre viene del POST
            if ($clienteId == 9999 && !empty($clienteNombre)) {
                $observaciones = ($observaciones ? $observaciones . ' | ' : '') . 'Cliente: ' . $clienteNombre;
            }

            $pagoId = $this->cobroModel->registrar([
                'cliente_id' => $clienteId,
                'cliente_nombre' => $clienteNombre,
                'monto' => $monto,
                'medio_pago' => $medioPago,
                'caja_banco_id' => $cajaBancoId,
                'observaciones' => $observaciones,
                'usuario_id' => $_SESSION['user_id'],
                'remito_id' => $remitoId,
            ]);

            $_SESSION['success'] = "Cobro #{$pagoId} registrado correctamente.";
            header("Location: " . BASE_URL . "/cobros/show/{$pagoId}");
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: " . BASE_URL . "/cobros/create");
            exit;
        }
    }

    /**
     * Anular un cobro.
     */
    public function anular(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . BASE_URL . "/cobros");
            exit;
        }

        try {
            $this->cobroModel->anular($id, $_SESSION['user_id']);
            $_SESSION['success'] = "Cobro #{$id} anulado correctamente.";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header("Location: " . BASE_URL . "/cobros/show/{$id}");
        exit;
    }

    /**
     * Ventas no cobradas: remitos con saldo pendiente de pago.
     */
    public function ventasNoCobradas(): void
    {
        $ventas = $this->ccModel->ventasNoCobradas();
        $cajas = $this->cajaModel->getActivas();
        $this->view('cobros/ventas_pendientes', [
            'title'  => 'Ventas No Cobradas',
            'ventas' => $ventas,
            'cajas'  => $cajas,
        ]);
    }
}
