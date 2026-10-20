<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Transferencia.php';
require_once BASE_PATH . '/app/models/Deposito.php';
require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/Materiaprima.php';

class TransferenciasController extends Controller
{
    private Transferencia $model;

    public function __construct()
    {
        $this->model = new Transferencia();
    }

    public function index(): void
    {
        $this->view('transferencias/index', [
            'title' => 'Transferencias entre Depósitos',
            'transferencias' => $this->model->all()
        ]);
    }

    public function create(): void
    {
        $depositos = (new Deposito())->allSeleccionables();
        $origenPreseleccionado = !empty($_GET['origen']) ? (int)$_GET['origen'] : null;

        $this->view('transferencias/create', [
            'title' => 'Nueva Transferencia',
            'depositos' => $depositos,
            'origen_preseleccionado' => $origenPreseleccionado
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/transferencias');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            header('Location: ' . BASE_URL . '/transferencias/create');
            exit;
        }

        try {
            $items = [];
            $tipos = $_POST['tipo'] ?? [];
            $cantidades = $_POST['cantidad'] ?? [];
            $lotes = $_POST['lote_id'] ?? [];
            $productoIds = $_POST['producto_id'] ?? [];
            $mpIds = $_POST['materia_prima_id'] ?? [];

            foreach ($cantidades as $index => $cantidad) {
                if (empty($cantidad) || (float)$cantidad <= 0) continue;

                $items[] = [
                    'tipo' => $tipos[$index] ?? 'producto',
                    'producto_id' => !empty($productoIds[$index]) ? (int)$productoIds[$index] : null,
                    'materia_prima_id' => !empty($mpIds[$index]) ? (int)$mpIds[$index] : null,
                    'cantidad' => (float)$cantidad,
                    'lote_id' => !empty($lotes[$index]) ? (int)$lotes[$index] : null
                ];
            }

            $depositos = (new Deposito())->all();
            $depositoOrigenNombre = '';
            $depositoDestinoNombre = '';
            foreach ($depositos as $d) {
                if ((int)$d['id'] === (int)$_POST['deposito_origen_id']) $depositoOrigenNombre = $d['nombre'];
                if ((int)$d['id'] === (int)$_POST['deposito_destino_id']) $depositoDestinoNombre = $d['nombre'];
            }

            $id = $this->model->create([
                'deposito_origen_id' => (int)$_POST['deposito_origen_id'],
                'deposito_destino_id' => (int)$_POST['deposito_destino_id'],
                'deposito_origen_nombre' => $depositoOrigenNombre,
                'deposito_destino_nombre' => $depositoDestinoNombre,
                'observaciones' => $_POST['observaciones'] ?? null,
                'items' => $items
            ], (int)$_SESSION['user_id']);

            $_SESSION['success'] = 'Transferencia creada correctamente.';
            header('Location: ' . BASE_URL . '/transferencias/show/' . $id);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log('Error al crear transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            header('Location: ' . BASE_URL . '/transferencias/create');
            exit;
        }
    }

    public function show(int $id): void
    {
        validarId($id, BASE_URL . '/transferencias');
        $transferencia = $this->model->find($id);

        if (!$transferencia) {
            $_SESSION['error'] = 'Transferencia no encontrada.';
            header('Location: ' . BASE_URL . '/transferencias');
            exit;
        }

        $this->view('transferencias/show', [
            'title' => 'Transferencia #' . $transferencia['numero'],
            'transferencia' => $transferencia
        ]);
    }

    public function anular(int $id): void
    {
        validarId($id, BASE_URL . '/transferencias');

        try {
            $this->model->anular($id, (int)$_SESSION['user_id']);
            $_SESSION['success'] = 'Transferencia anulada correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log('Error al anular transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
        }

        header('Location: ' . BASE_URL . '/transferencias/show/' . $id);
        exit;
    }

    public function recibir(int $id): void
    {
        validarId($id, BASE_URL . '/transferencias');

        try {
            $this->model->recibir($id, (int)$_SESSION['user_id']);
            $_SESSION['success'] = 'Mercadería recibida y acreditada en el depósito destino.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log('Error al recibir transferencia: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
        }

        header('Location: ' . BASE_URL . '/transferencias/show/' . $id);
        exit;
    }

    public function stockDeposito(): void
    {
        header('Content-Type: application/json');

        $depositoId = (int)($_GET['deposito_id'] ?? 0);
        $tipo = $_GET['tipo'] ?? 'producto';
        $id = (int)($_GET['id'] ?? 0);

        if ($depositoId <= 0 || $id <= 0) {
            echo json_encode(['stock' => 0]);
            return;
        }

        if ($tipo === 'materia_prima') {
            $stock = $this->model->stockPorDeposito($depositoId, null, $id);
            $stockTotal = $this->model->stockTotal(null, $id);
            $lotes = $this->model->lotesDisponibles($depositoId, null, $id);
        } else {
            $stock = $this->model->stockPorDeposito($depositoId, $id, null);
            $stockTotal = $this->model->stockTotal($id, null);
            $lotes = $this->model->lotesDisponibles($depositoId, $id, null);
        }

        echo json_encode([
            'stock' => $stock,
            'stock_total' => $stockTotal,
            'lotes' => $lotes
        ]);
    }
}