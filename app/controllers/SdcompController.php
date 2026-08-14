<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Sdcomp.php';

class SdcompController extends Controller
{
    private Sdcomp $model;

    public function __construct()
    {
        $this->model = new Sdcomp();
    }

    private function checkAccess(): void
    {
        Auth::requireTenant();
        if (!Auth::canSeeSdcomp()) {
            $_SESSION['error'] = 'No tienes acceso a esta seccion.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
        if (!$this->model->tablasExistentes()) {
            $_SESSION['error'] = 'Falta ejecutar la migracion 015. Ve a Admin > Migraciones y ejecuta la 015.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public function index()
    {
        $this->checkAccess();

        $filtros = [
            'tipo'       => $_GET['tipo'] ?? null,
            'estado'     => $_GET['estado'] ?? null,
            'fecha_desde'=> $_GET['fecha_desde'] ?? null,
            'fecha_hasta'=> $_GET['fecha_hasta'] ?? null,
            'buscar'     => $_GET['buscar'] ?? null,
        ];

        $movimientos = $this->model->all($filtros);

        $this->view('sdcomp/index', [
            'movimientos' => $movimientos,
            'filtros' => $filtros
        ]);
    }

    public function create()
    {
        $this->checkAccess();

        $productos = $this->model->getProductos();
        $clientes = $this->model->getClientes();
        $proveedores = $this->model->getProveedores();

        $this->view('sdcomp/create', [
            'productos' => $productos,
            'clientes' => $clientes,
            'proveedores' => $proveedores
        ]);
    }

    public function store()
    {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/sdcomp/create');
            exit;
        }

        try {
            $tipo = htmlspecialchars($_POST['tipo'] ?? '');
            if (!in_array($tipo, ['VENTA', 'COMPRA'])) {
                throw new Exception('Tipo de movimiento invalido');
            }

            if (empty($_POST['items']) || !is_array($_POST['items'])) {
                throw new Exception('Debe agregar al menos un producto');
            }

            $itemsLimpios = [];
            foreach ($_POST['items'] as $item) {
                $cant = (float)($item['cantidad'] ?? 0);
                if ($cant <= 0) continue;

                $productoId = !empty($item['producto_id']) ? $item['producto_id'] : null;
                $materiaPrimaId = !empty($item['materia_prima_id']) ? $item['materia_prima_id'] : null;
                $tipoItem = $item['tipo_item'] ?? 'PRODUCTO';
                $descripcion = !empty($item['descripcion']) ? trim($item['descripcion']) : null;

                // Para manuales, no se requiere producto ni materia prima
                if ($tipoItem !== 'MANUAL') {
                    if (!$productoId && !$materiaPrimaId) {
                        throw new Exception('Todos los items deben tener un producto, materia prima o ser un concepto manual');
                    }
                }

                $itemsLimpios[] = [
                    'producto_id' => $productoId,
                    'materia_prima_id' => $materiaPrimaId,
                    'descripcion' => $descripcion,
                    'cantidad' => $cant,
                    'precio_unitario' => (float)($item['precio_unitario'] ?? 0),
                    'tipo_item' => $tipoItem
                ];
            }

            if (empty($itemsLimpios)) {
                throw new Exception('Debe agregar al menos un producto con cantidad mayor a cero');
            }

            $clienteId = null;
            $proveedorId = null;
            $razonSocial = null;
            $cuit = null;

            if ($tipo === 'VENTA') {
                if (!empty($_POST['cliente_id'])) {
                    $clienteId = (int)$_POST['cliente_id'];
                }
                $razonSocial = htmlspecialchars($_POST['razon_social_ventas'] ?? '');
                $cuit = htmlspecialchars($_POST['cuit_ventas'] ?? '');
            } else {
                if (!empty($_POST['proveedor_id'])) {
                    $proveedorId = (int)$_POST['proveedor_id'];
                }
                $razonSocial = htmlspecialchars($_POST['razon_social_compras'] ?? '');
                $cuit = htmlspecialchars($_POST['cuit_compras'] ?? '');
            }

            $movId = $this->model->create(
                $tipo,
                $itemsLimpios,
                $clienteId,
                $proveedorId,
                $razonSocial,
                $cuit,
                htmlspecialchars($_POST['descripcion'] ?? ''),
                htmlspecialchars($_POST['observaciones'] ?? '')
            );

            $_SESSION['success'] = 'Comprobante #' . $movId . ' registrado correctamente.';
            header('Location: ' . BASE_URL . '/sdcomp/show/' . $movId);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/sdcomp/create');
            exit;
        }
    }

    public function show($id)
    {
        $this->checkAccess();

        $mov = $this->model->find((int)$id);
        if (!$mov) {
            $_SESSION['error'] = 'Comprobante no encontrado.';
            header('Location: ' . BASE_URL . '/sdcomp');
            exit;
        }

        $this->view('sdcomp/show', [
            'mov' => $mov
        ]);
    }

    public function generarPdf($id): void
    {
        $this->checkAccess();
        validarId($id, BASE_URL . '/sdcomp');
        try {
            $this->model->generarYGuardarPdf((int)$id);
            // Redirigir o descargar, dependiendo de la implementación deseada
            // Por ahora, solo se guarda, el enlace en la vista lo abre
            header('Location: ' . BASE_URL . '/sdcomp/show/' . $id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al generar el PDF: ' . $e->getMessage();
            error_log('Error al generar PDF de SDCOMP: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/sdcomp/show/' . $id);
            exit;
        }
    }

    public function pago($id)
    {
        $this->checkAccess();

        $mov = $this->model->find((int)$id);
        if (!$mov) {
            $_SESSION['error'] = 'Comprobante no encontrado.';
            header('Location: ' . BASE_URL . '/sdcomp');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $monto = (float)($_POST['monto'] ?? 0);
                if ($monto <= 0) {
                    throw new Exception('El monto debe ser mayor a cero');
                }

                $this->model->registrarPago(
                    (int)$id,
                    $monto,
                    htmlspecialchars($_POST['descripcion'] ?? '')
                );

                $msg = $mov['tipo'] === 'VENTA' ? 'Cobro registrado correctamente.' : 'Pago registrado correctamente.';
                $_SESSION['success'] = $msg;
                header('Location: ' . BASE_URL . '/sdcomp/show/' . $id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/sdcomp/show/' . $id);
                exit;
            }
        }

        $this->view('sdcomp/pago', [
            'mov' => $mov
        ]);
    }

    public function anular($id)
    {
        $this->checkAccess();

        try {
            $this->model->anular((int)$id);
            $_SESSION['success'] = 'Comprobante anulado y stock ajustado.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/sdcomp');
        exit;
    }

    public function dashboard()
    {
        $this->checkAccess();

        $datos = $this->model->dashboard();

        $this->view('sdcomp/dashboard', [
            'datos' => $datos
        ]);
    }

    public function search()
    {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        $items = $this->model->searchItems($q);
        echo json_encode($items);
    }
}
