<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Gasto.php';
require_once BASE_PATH.'/app/core/Csrf.php';

class GastosController extends Controller{

    private Gasto $model;

    public function __construct(){
        $this->model = new Gasto();
    }

    /**
     * Listado de gastos con filtros.
     */
    public function index(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $filters = [
            'categoria'   => $_GET['categoria'] ?? '',
            'estado'      => $_GET['estado'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'buscar'      => $_GET['buscar'] ?? '',
        ];

        $gastos = $this->model->getAll($filters);
        $estadosCount = $this->model->countByEstado();

        $this->view('gastos/index', [
            'title'       => 'Gastos',
            'gastos'      => $gastos,
            'filters'     => $filters,
            'estadosCount'=> $estadosCount,
        ]);
    }

    /**
     * Formulario de nuevo gasto.
     */
    public function create(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $ocPendientes = $this->model->getOCPendientes();

        $this->view('gastos/form', [
            'title'       => 'Nuevo Gasto',
            'gasto'       => null,
            'ocPendientes'=> $ocPendientes,
            'csrf'        => Csrf::generate(),
        ]);
    }

    /**
     * Guardar nuevo gasto.
     */
    public function store(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . '/gastos/create');
            exit;
        }

        $data = $this->sanitizeInput($_POST);

        if (empty($data['fecha']) || empty($data['categoria']) || empty($data['descripcion']) || empty($data['monto_total'])) {
            $_SESSION['error'] = 'Fecha, categoría, descripción y monto son obligatorios.';
            header('Location: ' . BASE_URL . '/gastos/create');
            exit;
        }

        $data['usuario_id'] = $_SESSION['user_id'];
        $data['estado'] = 'BORRADOR';

        $id = $this->model->create($data);

        $_SESSION['success'] = "Gasto #{$id} registrado correctamente.";
        header('Location: ' . BASE_URL . '/gastos');
        exit;
    }

    /**
     * Ver detalle de un gasto.
     */
    public function show(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $gasto = $this->model->findById($id);
        if (!$gasto) {
            $_SESSION['error'] = 'Gasto no encontrado.';
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }

        $this->view('gastos/show', [
            'title' => "Gasto #{$id}",
            'gasto' => $gasto,
        ]);
    }

    /**
     * Formulario de edición (solo BORRADOR).
     */
    public function edit(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $gasto = $this->model->findById($id);
        if (!$gasto) {
            $_SESSION['error'] = 'Gasto no encontrado.';
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }
        if ($gasto['estado'] !== 'BORRADOR') {
            $_SESSION['error'] = 'Solo se pueden editar gastos en estado Borrador.';
            header('Location: ' . BASE_URL . '/gastos/show/' . $id);
            exit;
        }

        $ocPendientes = $this->model->getOCPendientes();

        $this->view('gastos/form', [
            'title'       => "Editar Gasto #{$id}",
            'gasto'       => $gasto,
            'ocPendientes'=> $ocPendientes,
            'csrf'        => Csrf::generate(),
        ]);
    }

    /**
     * Actualizar gasto.
     */
    public function update(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . "/gastos/edit/{$id}");
            exit;
        }

        $gasto = $this->model->findById($id);
        if (!$gasto || $gasto['estado'] !== 'BORRADOR') {
            $_SESSION['error'] = 'No se puede editar este gasto.';
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }

        $data = $this->sanitizeInput($_POST);

        if (empty($data['fecha']) || empty($data['categoria']) || empty($data['descripcion']) || empty($data['monto_total'])) {
            $_SESSION['error'] = 'Campos obligatorios incompletos.';
            header('Location: ' . BASE_URL . "/gastos/edit/{$id}");
            exit;
        }

        $this->model->update($id, $data);

        $_SESSION['success'] = "Gasto #{$id} actualizado.";
        header('Location: ' . BASE_URL . '/gastos/show/' . $id);
        exit;
    }

    /**
     * Cambiar estado a APROBADO.
     */
    public function aprobar(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();
        $this->cambiarEstadoSeguro($id, 'APROBADO', 'aprobado');
    }

    /**
     * Cambiar estado a PAGADO.
     */
    public function pagar(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();
        $this->cambiarEstadoSeguro($id, 'PAGADO', 'pagado');
    }

    /**
     * Cambiar estado a ANULADO.
     */
    public function anular(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();
        $this->cambiarEstadoSeguro($id, 'ANULADO', 'anulado');
    }

    /**
     * Dashboard: resumen mensual + por categoría.
     */
    public function dashboard(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $mes = (int)($_GET['mes'] ?? date('n'));
        $año = (int)($_GET['año'] ?? date('Y'));

        $resumen = $this->model->getResumenMensual($mes, $año);
        $porProveedor = $this->model->getPorProveedor($mes, $año);
        $recientes = $this->model->getRecientes(5);
        $estadosCount = $this->model->countByEstado();

        $this->view('gastos/dashboard', [
            'title'         => 'Dashboard de Gastos',
            'mes'           => $mes,
            'año'           => $año,
            'resumen'       => $resumen,
            'porProveedor'  => $porProveedor,
            'recientes'     => $recientes,
            'estadosCount'  => $estadosCount,
        ]);
    }

    // =====================================================
    // HELPERS PRIVADOS
    // =====================================================

    private function cambiarEstadoSeguro(int $id, string $nuevoEstado, string $accion): void{
        $gasto = $this->model->findById($id);
        if (!$gasto) {
            $_SESSION['error'] = 'Gasto no encontrado.';
            header('Location: ' . BASE_URL . '/gastos');
            exit;
        }

        $transiciones = [
            'BORRADOR'  => ['APROBADO', 'ANULADO'],
            'APROBADO'  => ['PAGADO', 'ANULADO'],
            'PAGADO'    => ['ANULADO'],
            'ANULADO'   => [],
        ];

        $permitidos = $transiciones[$gasto['estado']] ?? [];
        if (!in_array($nuevoEstado, $permitidos)) {
            $_SESSION['error'] = "No se puede cambiar de '{$gasto['estado']}' a '{$nuevoEstado}'.";
            header('Location: ' . BASE_URL . '/gastos/show/' . $id);
            exit;
        }

        $this->model->cambiarEstado($id, $nuevoEstado);

        $_SESSION['success'] = "Gasto #{$id} {$accion} correctamente.";
        header('Location: ' . BASE_URL . '/gastos/show/' . $id);
        exit;
    }

    private function sanitizeInput(array $input): array{
        return [
            'fecha'           => trim($input['fecha'] ?? ''),
            'categoria'       => trim($input['categoria'] ?? ''),
            'descripcion'     => trim($input['descripcion'] ?? ''),
            'orden_compra_id' => !empty($input['orden_compra_id']) ? (int)$input['orden_compra_id'] : null,
            'monto_total'     => (float)($input['monto_total'] ?? 0),
            'medio_pago'      => trim($input['medio_pago'] ?? 'TRANSFERENCIA'),
            'comprobante'     => trim($input['comprobante'] ?? ''),
            'observaciones'   => trim($input['observaciones'] ?? ''),
        ];
    }
}
