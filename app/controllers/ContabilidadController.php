<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/CuentaContable.php';
require_once BASE_PATH.'/app/models/AsientoContable.php';
require_once BASE_PATH.'/app/models/CajaBanco.php';
require_once BASE_PATH.'/app/models/ConciliacionBancaria.php';
require_once BASE_PATH.'/app/models/Impuesto.php';
require_once BASE_PATH.'/app/core/Csrf.php';

class ContabilidadController extends Controller{

    private CuentaContable $cuentaModel;
    private AsientoContable $asientoModel;
    private CajaBanco $cajaModel;
    private ConciliacionBancaria $conciliacionModel;
    private Impuesto $impuestoModel;

    public function __construct(){
        $this->cuentaModel = new CuentaContable();
        $this->asientoModel = new AsientoContable();
        $this->cajaModel = new CajaBanco();
        $this->conciliacionModel = new ConciliacionBancaria();
        $this->impuestoModel = new Impuesto();
    }

    private function requireAdmin(): void{
        Auth::requireLogin();
        Auth::requireTenant();
        if (!Auth::isEmpresaAdmin()) {
            $_SESSION['error'] = 'No tienes permisos de administrador.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    // =====================================================
    // PLAN DE CUENTAS
    // =====================================================

    public function planCuentas(): void{
        $this->requireAdmin();
        $arbol = $this->cuentaModel->getArbol();
        $this->view('contabilidad/plan_cuentas', [
            'title' => 'Plan de Cuentas',
            'arbol' => $arbol,
        ]);
    }

    public function cuentaSave(): void{
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
            exit;
        }
        if (!Csrf::validate($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
            exit;
        }

        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $data = [
            'codigo'            => trim($_POST['codigo']),
            'nombre'            => trim($_POST['nombre']),
            'tipo'              => $_POST['tipo'],
            'nivel'             => (int)($_POST['nivel'] ?? 1),
            'acepta_movimiento' => (int)($_POST['acepta_movimiento'] ?? 1),
            'activa'            => 1,
        ];

        if (empty($data['codigo']) || empty($data['nombre']) || empty($data['tipo'])) {
            $_SESSION['error'] = 'Código, nombre y tipo son obligatorios.';
            header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
            exit;
        }

        if ($id) {
            $data['activa'] = 1;
            $this->cuentaModel->update($id, $data);
            $_SESSION['success'] = 'Cuenta actualizada.';
        } else {
            $data['padre_id'] = !empty($_POST['padre_id']) ? (int)$_POST['padre_id'] : null;
            $this->cuentaModel->create($data);
            $_SESSION['success'] = 'Cuenta creada.';
        }

        header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
        exit;
    }

    public function cuentaToggle(int $id): void{
        $this->requireAdmin();
        $cuenta = $this->cuentaModel->findById($id);
        if (!$cuenta) {
            $_SESSION['error'] = 'Cuenta no encontrada.';
            header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
            exit;
        }
        $this->cuentaModel->update($id, [
            'codigo'            => $cuenta['codigo'],
            'nombre'            => $cuenta['nombre'],
            'tipo'              => $cuenta['tipo'],
            'acepta_movimiento' => $cuenta['acepta_movimiento'],
            'activa'            => $cuenta['activa'] ? 0 : 1,
        ]);
        $accion = $cuenta['activa'] ? 'desactivada' : 'activada';
        $_SESSION['success'] = "Cuenta {$accion}.";
        header('Location: ' . BASE_URL . '/contabilidad/plan-cuentas');
        exit;
    }

    // =====================================================
    // ASIENTOS CONTABLES (LIBRO DIARIO)
    // =====================================================

    public function asientos(): void{
        $this->requireAdmin();
        $filters = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'tipo'        => $_GET['tipo'] ?? '',
            'buscar'      => $_GET['buscar'] ?? '',
        ];
        $asientos = $this->asientoModel->getAll($filters);

        $this->view('contabilidad/asientos', [
            'title'   => 'Libro Diario (Asientos Contables)',
            'asientos'=> $asientos,
            'filters' => $filters,
        ]);
    }

    public function asientoCreate(): void{
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . '/contabilidad/asiento-create');
                exit;
            }

            $lineas = [];
            $i = 0;
            while (isset($_POST['cuenta_id'][$i])) {
                if (!empty($_POST['cuenta_id'][$i])) {
                    $lineas[] = [
                        'cuenta_contable_id' => (int)$_POST['cuenta_id'][$i],
                        'debe'               => (float)($_POST['debe'][$i] ?? 0),
                        'haber'              => (float)($_POST['haber'][$i] ?? 0),
                    ];
                }
                $i++;
            }

            if (count($lineas) < 2) {
                $_SESSION['error'] = 'Un asiento debe tener al menos 2 líneas.';
                header('Location: ' . BASE_URL . '/contabilidad/asiento-create');
                exit;
            }

            try {
                $asientoId = $this->asientoModel->create([
                    'fecha'         => $_POST['fecha'],
                    'descripcion'   => $_POST['descripcion'],
                    'tipo'          => $_POST['tipo'] ?? 'OPERACION',
                    'usuario_id'    => $_SESSION['user_id'],
                    'observaciones' => $_POST['observaciones'] ?? null,
                ], $lineas);

                $_SESSION['success'] = "Asiento #{$asientoId} creado correctamente.";
                header('Location: ' . BASE_URL . '/contabilidad/asiento-show/' . $asientoId);
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/contabilidad/asiento-create');
                exit;
            }
        }

        $cuentas = $this->cuentaModel->getHojas();
        $this->view('contabilidad/asiento_form', [
            'title'   => 'Nuevo Asiento Contable',
            'asiento' => null,
            'cuentas' => $cuentas,
            'csrf'    => Csrf::generate(),
            'proximoNumero' => $this->asientoModel->proximoNumero(),
        ]);
    }

    public function asientoShow(int $id): void{
        $this->requireAdmin();
        $asiento = $this->asientoModel->findById($id);
        if (!$asiento) {
            $_SESSION['error'] = 'Asiento no encontrado.';
            header('Location: ' . BASE_URL . '/contabilidad/asientos');
            exit;
        }
        $this->view('contabilidad/asiento_show', [
            'title'  => "Asiento #{$asiento['numero']}",
            'asiento'=> $asiento,
        ]);
    }

    public function asientoAnular(int $id): void{
        $this->requireAdmin();
        try {
            $nuevoId = $this->asientoModel->anular($id, $_SESSION['user_id']);
            $_SESSION['success'] = "Asiento anulado. Se generó el asiento inverso #{$nuevoId}.";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/contabilidad/asientos');
        exit;
    }

    // =====================================================
    // CAJAS, BANCOS Y FONDOS
    // =====================================================

    public function cajas(): void{
        $this->requireAdmin();
        $cajas = $this->cajaModel->getAll();
        $resumen = $this->cajaModel->getResumenSaldos();

        $this->view('contabilidad/cajas', [
            'title'  => 'Cajas, Bancos y Fondos',
            'cajas'  => $cajas,
            'resumen'=> $resumen,
            'csrf'   => Csrf::generate(),
        ]);
    }

    public function cajaCreate(): void{
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF inválido.';
                header('Location: ' . BASE_URL . '/contabilidad/cajas');
                exit;
            }
            $this->cajaModel->create($_POST);
            $_SESSION['success'] = 'Caja/Banco creado.';
            header('Location: ' . BASE_URL . '/contabilidad/cajas');
            exit;
        }
        header('Location: ' . BASE_URL . '/contabilidad/cajas');
        exit;
    }

    public function cajaDetalle(int $id): void{
        $this->requireAdmin();
        $caja = $this->cajaModel->findById($id);
        if (!$caja) {
            $_SESSION['error'] = 'Caja/Banco no encontrado.';
            header('Location: ' . BASE_URL . '/contabilidad/cajas');
            exit;
        }

        $filters = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];
        $movimientos = $this->cajaModel->getMovimientos($id, $filters);

        $this->view('contabilidad/caja_detalle', [
            'title'      => "Detalle: {$caja['nombre']}",
            'caja'       => $caja,
            'movimientos'=> $movimientos,
            'filters'    => $filters,
        ]);
    }

    // =====================================================
    // CONCILIACIÓN BANCARIA
    // =====================================================

    public function conciliacion(): void{
        $this->requireAdmin();
        $cajas = $this->cajaModel->getActivas();
        $cajaId = (int)($_GET['caja_id'] ?? 0);
        $conciliaciones = [];
        $movimientos = [];

        if ($cajaId) {
            $conciliaciones = $this->conciliacionModel->getAll($cajaId);
            $movimientos = $this->conciliacionModel->getMovimientosNoConciliados($cajaId);
        }

        $this->view('contabilidad/conciliacion', [
            'title'         => 'Conciliación Bancaria',
            'cajas'         => $cajas,
            'cajaId'        => $cajaId,
            'conciliaciones'=> $conciliaciones,
            'movimientos'   => $movimientos,
        ]);
    }

    // =====================================================
    // BALANCE GENERAL
    // =====================================================

    public function balance(): void{
        $this->requireAdmin();
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-01-01');
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

        $saldos = $this->cuentaModel->getSaldosPorTipo($fechaDesde, $fechaHasta);

        $activo = [];
        $pasivo = [];
        $patrimonio = [];
        $ingresos = [];
        $egresos = [];

        foreach ($saldos as $item) {
            $tipo = $item['cuenta']['tipo'];
            switch ($tipo) {
                case 'ACTIVO':   $activo[] = $item; break;
                case 'PASIVO':   $pasivo[] = $item; break;
                case 'PATRIMONIO': $patrimonio[] = $item; break;
                case 'INGRESO':  $ingresos[] = $item; break;
                case 'EGRESO':   $egresos[] = $item; break;
            }
        }

        $totalActivo = array_sum(array_column($activo, 'saldo'));
        $totalPasivo = array_sum(array_column($pasivo, 'saldo'));
        $totalPatrimonio = array_sum(array_column($patrimonio, 'saldo'));

        $this->view('contabilidad/balance', [
            'title'           => 'Balance General',
            'fechaDesde'      => $fechaDesde,
            'fechaHasta'      => $fechaHasta,
            'activo'          => $activo,
            'pasivo'          => $pasivo,
            'patrimonio'      => $patrimonio,
            'totalActivo'     => $totalActivo,
            'totalPasivo'     => $totalPasivo,
            'totalPatrimonio' => $totalPatrimonio,
        ]);
    }

    // =====================================================
    // ESTADO DE RESULTADOS
    // =====================================================

    public function resultados(): void{
        $this->requireAdmin();
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-01-01');
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

        $saldos = $this->cuentaModel->getSaldosPorTipo($fechaDesde, $fechaHasta);

        $ingresos = [];
        $egresos = [];

        foreach ($saldos as $item) {
            if ($item['cuenta']['tipo'] === 'INGRESO') $ingresos[] = $item;
            if ($item['cuenta']['tipo'] === 'EGRESO')  $egresos[] = $item;
        }

        $totalIngresos = array_sum(array_column($ingresos, 'saldo'));
        $totalEgresos = abs(array_sum(array_column($egresos, 'saldo')));
        $resultado = $totalIngresos - $totalEgresos;

        $this->view('contabilidad/resultados', [
            'title'          => 'Estado de Resultados',
            'fechaDesde'     => $fechaDesde,
            'fechaHasta'     => $fechaHasta,
            'ingresos'       => $ingresos,
            'egresos'        => $egresos,
            'totalIngresos'  => $totalIngresos,
            'totalEgresos'   => $totalEgresos,
            'resultado'      => $resultado,
        ]);
    }

    // =====================================================
    // IMPUESTOS (IVA)
    // =====================================================

    public function impuestos(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $impuestos = $this->impuestoModel->getAll();

        $this->view('contabilidad/impuestos', [
            'title'     => 'Impuestos (IVA)',
            'impuestos' => $impuestos,
            'csrf'      => Csrf::generate(),
        ]);
    }

    public function impuestoSave(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        $id       = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $nombre   = trim($_POST['nombre'] ?? '');
        $codigo   = strtoupper(trim($_POST['codigo'] ?? ''));
        $porcentaje = (float)($_POST['porcentaje'] ?? 0);

        if (empty($nombre) || empty($codigo)) {
            $_SESSION['error'] = 'Nombre y código son obligatorios.';
            header('Location: ' . BASE_URL . '/contabilidad/impuestos');
            exit;
        }

        $data = [
            'nombre'     => $nombre,
            'codigo'     => $codigo,
            'porcentaje' => $porcentaje,
            'activo'     => 1,
        ];

        if ($id) {
            $this->impuestoModel->update($id, $data);
            $_SESSION['success'] = "Impuesto #{$id} actualizado.";
        } else {
            $this->impuestoModel->create($data);
            $_SESSION['success'] = "Impuesto creado.";
        }

        header('Location: ' . BASE_URL . '/contabilidad/impuestos');
        exit;
    }

    public function impuestoToggle(int $id): void{
        Auth::requireLogin();
        Auth::requireTenant();

        $this->impuestoModel->toggle($id);
        $_SESSION['success'] = "Impuesto #{$id} actualizado.";
        header('Location: ' . BASE_URL . '/contabilidad/impuestos');
        exit;
    }

    // =====================================================
    // CAJAS / BANCOS - CREAR
    // =====================================================

    public function cajaSave(): void{
        Auth::requireLogin();
        Auth::requireTenant();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contabilidad/cajas');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF inválido.';
            header('Location: ' . BASE_URL . '/contabilidad/cajas');
            exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $tipo   = trim($_POST['tipo'] ?? '');

        if (empty($nombre) || empty($tipo)) {
            $_SESSION['error'] = 'Nombre y tipo son obligatorios.';
            header('Location: ' . BASE_URL . '/contabilidad/cajas');
            exit;
        }

        $this->cajaModel->create([
            'nombre'             => $nombre,
            'tipo'               => $tipo,
            'banco'              => trim($_POST['banco'] ?? '') ?: null,
            'numero_cuenta'      => trim($_POST['numero_cuenta'] ?? '') ?: null,
            'saldo_inicial'      => (float)($_POST['saldo_inicial'] ?? 0),
            'moneda'             => trim($_POST['moneda'] ?? 'ARS'),
            'cuenta_contable_id' => !empty($_POST['cuenta_contable_id']) ? (int)$_POST['cuenta_contable_id'] : null,
        ]);

        $_SESSION['success'] = "Caja/Banco '{$nombre}' creado.";
        header('Location: ' . BASE_URL . '/contabilidad/cajas');
        exit;
    }
}
