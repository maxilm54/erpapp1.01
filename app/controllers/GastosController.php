<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Gasto.php';
require_once BASE_PATH.'/app/models/Impuesto.php';
require_once BASE_PATH.'/app/models/CajaBanco.php';
require_once BASE_PATH.'/app/helpers/AsientoAutomatico.php';
require_once BASE_PATH.'/app/core/Csrf.php';

class GastosController extends Controller{

    private Gasto $model;
    private Impuesto $impuestoModel;
    private CajaBanco $cajaModel;
    private AsientoAutomatico $asientoAuto;

    public function __construct(){
        $this->model = new Gasto();
        $this->impuestoModel = new Impuesto();
        $this->cajaModel = new CajaBanco();
        $this->asientoAuto = new AsientoAutomatico();
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
        $impuestos = $this->impuestoModel->getActivos();

        $this->view('gastos/form', [
            'title'       => 'Nuevo Gasto',
            'gasto'       => null,
            'ocPendientes'=> $ocPendientes,
            'impuestos'   => $impuestos,
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

        // Validar saldo de OC si está vinculada
        if (!empty($data['orden_compra_id'])) {
            $ocSaldo = $this->model->getOCSaldo($data['orden_compra_id']);
            if ($ocSaldo && $data['monto_total'] > $ocSaldo['saldo_pendiente']) {
                $_SESSION['error'] = "El monto (\$ " . number_format($data['monto_total'], 2, ',', '.') . ") excede el saldo pendiente de la OC (\$ " . number_format($ocSaldo['saldo_pendiente'], 2, ',', '.') . ").";
                header('Location: ' . BASE_URL . '/gastos/create');
                exit;
            }
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

        $cajasBancos = $this->cajaModel->getActivas();

        $this->view('gastos/show', [
            'title' => "Gasto #{$id}",
            'gasto' => $gasto,
            'cajasBancos' => $cajasBancos,
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
        $impuestos = $this->impuestoModel->getActivos();

        $this->view('gastos/form', [
            'title'       => "Editar Gasto #{$id}",
            'gasto'       => $gasto,
            'ocPendientes'=> $ocPendientes,
            'impuestos'   => $impuestos,
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

        // Validar saldo de OC si está vinculada
        if (!empty($data['orden_compra_id'])) {
            $ocSaldo = $this->model->getOCSaldo($data['orden_compra_id']);
            if ($ocSaldo) {
                // Si el gasto actual ya estaba vinculado a esta OC, sumar su monto al disponible
                $montoDisponible = $ocSaldo['saldo_pendiente'];
                if ((int)$gasto['orden_compra_id'] === (int)$data['orden_compra_id']) {
                    $montoDisponible += (float)$gasto['monto_total'];
                }
                if ($data['monto_total'] > $montoDisponible) {
                    $_SESSION['error'] = "El monto (\$ " . number_format($data['monto_total'], 2, ',', '.') . ") excede el saldo disponible de la OC (\$ " . number_format($montoDisponible, 2, ',', '.') . ").";
                    header('Location: ' . BASE_URL . "/gastos/edit/{$id}");
                    exit;
                }
            }
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

        // Si se paga: requerir caja_banco_id y generar asiento
        if ($nuevoEstado === 'PAGADO') {
            $cajaBancoId = !empty($_POST['caja_banco_id']) ? (int)$_POST['caja_banco_id'] : null;
            if (!$cajaBancoId) {
                $_SESSION['error'] = 'Debe seleccionar una Caja/Banco para registrar el pago.';
                header('Location: ' . BASE_URL . '/gastos/show/' . $id);
                exit;
            }

            // Verificar que la caja/banco existe y está activa
            $caja = $this->cajaModel->findById($cajaBancoId);
            if (!$caja || !$caja['activa']) {
                $_SESSION['error'] = 'La Caja/Banco seleccionada no es válida.';
                header('Location: ' . BASE_URL . '/gastos/show/' . $id);
                exit;
            }

            // Generar asiento contable automático
            try {
                $asientoId = $this->asientoAuto->gastoPagar($gasto, $cajaBancoId, $_SESSION['user_id']);
                $this->model->update($id, [
                    'fecha'           => $gasto['fecha'],
                    'categoria'       => $gasto['categoria'],
                    'descripcion'     => $gasto['descripcion'],
                    'orden_compra_id' => $gasto['orden_compra_id'],
                    'monto_total'     => $gasto['monto_total'],
                    'medio_pago'      => $gasto['medio_pago'],
                    'comprobante'     => $gasto['comprobante'],
                    'observaciones'   => $gasto['observaciones'],
                    'impuesto_id'     => $gasto['impuesto_id'],
                    'monto_base'      => $gasto['monto_base'],
                    'monto_impuesto'  => $gasto['monto_impuesto'],
                    'caja_banco_id'   => $cajaBancoId,
                ]);
                $_SESSION['success'] = "Gasto #{$id} pagado. Asiento #{$asientoId} generado.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error al generar asiento: " . $e->getMessage();
                header('Location: ' . BASE_URL . '/gastos/show/' . $id);
                exit;
            }
        }

        // Si se anula un gasto ya pagado: reversar asiento
        if ($nuevoEstado === 'ANULADO' && $gasto['estado'] === 'PAGADO') {
            try {
                $this->asientoAuto->gastoAnularPago($gasto, $_SESSION['user_id']);
                $_SESSION['success'] = "Gasto #{$id} anulado. Asiento de pago reversado.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error al reversar asiento: " . $e->getMessage();
                header('Location: ' . BASE_URL . '/gastos/show/' . $id);
                exit;
            }
        }

        if ($nuevoEstado !== 'PAGADO') {
            $this->model->cambiarEstado($id, $nuevoEstado);
        } else {
            $this->model->cambiarEstado($id, $nuevoEstado);
        }

        if ($nuevoEstado !== 'PAGADO' && $nuevoEstado !== 'ANULADO') {
            $_SESSION['success'] = "Gasto #{$id} {$accion} correctamente.";
        } elseif ($nuevoEstado === 'ANULADO') {
            $_SESSION['success'] = "Gasto #{$id} anulado correctamente.";
        }
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
            'impuesto_id'     => !empty($input['impuesto_id']) ? (int)$input['impuesto_id'] : null,
            'monto_base'      => !empty($input['monto_base']) ? (float)$input['monto_base'] : null,
            'monto_impuesto'  => !empty($input['monto_impuesto']) ? (float)$input['monto_impuesto'] : null,
            'caja_banco_id'   => !empty($input['caja_banco_id']) ? (int)$input['caja_banco_id'] : null,
        ];
    }
}
