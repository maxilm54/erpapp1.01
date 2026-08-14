<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/CreditoBancario.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/models/AsientoContable.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';
require_once BASE_PATH . '/app/core/Csrf.php';

class CreditosController extends Controller {

    private function requireModule(): void{
        Auth::requireLogin();
        Auth::requireTenant();
    }

    public function index(): void{
        $this->requireModule();
        $model = new CreditoBancario();
        $creditos = $model->getAll();

        $this->view('creditos/index', [
            'title'    => 'Creditos Bancarios',
            'creditos' => $creditos
        ]);
    }

    public function dashboard(): void{
        $this->requireModule();
        $model = new CreditoBancario();
        $data = $model->getDashboard();

        $this->view('creditos/dashboard', [
            'title' => 'Dashboard Creditos',
            'creditos' => $data['creditos'],
            'cuotas'   => $data['cuotas'],
            'proximas' => $data['proximas'],
        ]);
    }

    public function create(): void{
        $this->requireModule();

        $cajaModel = new CajaBanco();
        $cajas = $cajaModel->getActivas();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/creditos/create');
                exit;
            }

            $montoOriginal = (float)($_POST['monto_original'] ?? 0);
            $cantidadCuotas = (int)($_POST['cantidad_cuotas'] ?? 1);
            $tasaInteres = (float)($_POST['tasa_interes'] ?? 0);
            $cajaBancoId = (int)($_POST['caja_banco_id'] ?? 0);

            if ($montoOriginal <= 0) {
                $_SESSION['error'] = 'El monto debe ser mayor a 0.';
                header('Location: ' . BASE_URL . '/creditos/create');
                exit;
            }

            if ($cantidadCuotas <= 0) {
                $_SESSION['error'] = 'La cantidad de cuotas debe ser mayor a 0.';
                header('Location: ' . BASE_URL . '/creditos/create');
                exit;
            }

            if ($cajaBancoId <= 0) {
                $_SESSION['error'] = 'Selecciona una cuenta bancaria.';
                header('Location: ' . BASE_URL . '/creditos/create');
                exit;
            }

            $model = new CreditoBancario();

            // Calcular cuota mensual
            $tasaMensual = $tasaInteres > 0 ? ($tasaInteres / 12 / 100) : 0;
            if ($tasaMensual > 0) {
                $montoCuota = $montoOriginal * ($tasaMensual * pow(1 + $tasaMensual, $cantidadCuotas))
                            / (pow(1 + $tasaMensual, $cantidadCuotas) - 1);
            } else {
                $montoCuota = $montoOriginal / $cantidadCuotas;
            }

            // Fecha de vencimiento (ultima cuota)
            $fechaDesembolso = $_POST['fecha_desembolso'] ?? date('Y-m-d');
            $fechaVenc = date('Y-m-d', strtotime($fechaDesembolso . " +{$cantidadCuotas} months"));

            $creditoId = $model->create([
                'caja_banco_id'    => $cajaBancoId,
                'entidad'          => trim($_POST['entidad'] ?? ''),
                'monto_original'   => $montoOriginal,
                'tasa_interes'     => $tasaInteres,
                'cantidad_cuotas'  => $cantidadCuotas,
                'monto_cuota'      => round($montoCuota, 2),
                'fecha_desembolso' => $fechaDesembolso,
                'fecha_vencimiento' => $fechaVenc,
                'tipo'             => $_POST['tipo'] ?? 'FIJO',
                'moneda'           => $_POST['moneda'] ?? 'ARS',
                'observaciones'    => trim($_POST['observaciones'] ?? ''),
                'usuario_id'       => $_SESSION['user_id'],
            ]);

            // Generar cuotas
            $model->generateCuotas($creditoId);

            // Registrar desembolso en caja/banco (INGRESO)
            $cajaModel = new CajaBanco();
            $cajaModel->registrarMovimiento([
                'caja_banco_id' => $cajaBancoId,
                'fecha'         => $fechaDesembolso,
                'tipo'          => 'INGRESO',
                'monto'         => $montoOriginal,
                'descripcion'   => "Desembolso credito #{$creditoId}: " . trim($_POST['entidad'] ?? ''),
                'referencia_modulo' => 'CREDITOS',
                'referencia_tipo'   => 'DESEMBOLSO',
                'referencia_id'     => $creditoId,
                'usuario_id'        => $_SESSION['user_id'],
            ]);

            // Asiento contable: DEBE Banco, HABER Deudas Financieras
            try {
                $asiento = new AsientoAutomatico();
                $cuentaModel = new CuentaContable();
                $cuentaBanco = null;
                $caja = $cajaModel->findById($cajaBancoId);
                if ($caja && $caja['cuenta_contable_id']) {
                    $cuentaBanco = $cuentaModel->findById($caja['cuenta_contable_id']);
                }
                if (!$cuentaBanco) $cuentaBanco = $cuentaModel->findByCodigo('1101');
                $cuentaDeudas = $cuentaModel->findByCodigo('2400');

                if ($cuentaBanco && $cuentaDeudas) {
                    $asientoModel = new AsientoContable();
                    $asientoModel->create([
                        'fecha'          => $fechaDesembolso,
                        'descripcion'    => "Desembolso credito #{$creditoId}: " . trim($_POST['entidad'] ?? ''),
                        'tipo'           => 'OPERACION',
                        'origen_modulo'  => 'CREDITOS',
                        'origen_tipo'    => 'DESEMBOLSO',
                        'origen_id'      => $creditoId,
                        'usuario_id'     => $_SESSION['user_id'],
                    ], [
                        ['cuenta_contable_id' => $cuentaBanco['id'], 'debe' => $montoOriginal, 'haber' => 0],
                        ['cuenta_contable_id' => $cuentaDeudas['id'], 'debe' => 0, 'haber' => $montoOriginal],
                    ]);
                }
            } catch (Exception $e) {
                empresaLog("Error asiento credito: " . $e->getMessage(), 'ERROR');
            }

            $_SESSION['success'] = "Credito registrado. {$cantidadCuotas} cuotas generadas.";
            header('Location: ' . BASE_URL . '/creditos');
            exit;
        }

        $this->view('creditos/form', [
            'title'  => 'Nuevo Credito Bancario',
            'cajas'  => $cajas,
            'credito' => null,
            'csrf'   => Csrf::generate()
        ]);
    }

    public function show(int $id): void{
        $this->requireModule();

        $model = new CreditoBancario();
        $credito = $model->findById($id);

        if (!$credito) {
            $_SESSION['error'] = 'Credito no encontrado.';
            header('Location: ' . BASE_URL . '/creditos');
            exit;
        }

        $cuotas = $model->getCuotas($id);
        $cajaModel = new CajaBanco();
        $cajas = $cajaModel->getActivas();

        $this->view('creditos/show', [
            'title'   => 'Credito #' . $id,
            'credito' => $credito,
            'cuotas'  => $cuotas,
            'cajas'   => $cajas,
            'csrf'    => Csrf::generate()
        ]);
    }

    public function pagarCuota(): void{
        $this->requireModule();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/creditos');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF invalido.';
            header('Location: ' . BASE_URL . '/creditos');
            exit;
        }

        $cuotaId = (int)($_POST['cuota_id'] ?? 0);
        $cajaBancoId = (int)($_POST['caja_banco_id'] ?? 0);
        $creditoId = (int)($_POST['credito_id'] ?? 0);

        if ($cuotaId <= 0 || $cajaBancoId <= 0) {
            $_SESSION['error'] = 'Datos invalidos.';
            header('Location: ' . BASE_URL . "/creditos/show/{$creditoId}");
            exit;
        }

        $model = new CreditoBancario();

        try {
            $cuota = $model->pagarCuota($cuotaId, $cajaBancoId, $_SESSION['user_id']);

            // Registrar EGRESO en caja/banco
            $cajaModel = new CajaBanco();
            $cajaModel->registrarMovimiento([
                'caja_banco_id' => $cajaBancoId,
                'fecha'         => date('Y-m-d'),
                'tipo'          => 'EGRESO',
                'monto'         => (float)$cuota['monto'],
                'descripcion'   => "Pago cuota #{$cuota['numero_cuota']} credito #{$creditoId}",
                'referencia_modulo' => 'CREDITOS',
                'referencia_tipo'   => 'CUOTA',
                'referencia_id'     => $cuotaId,
                'usuario_id'        => $_SESSION['user_id'],
            ]);

            // Asiento contable: DEBE Deudas Financieras, HABER Banco
            try {
                $cuentaModel = new CuentaContable();
                $credito = $model->findById($creditoId);
                $cuentaBanco = null;
                $caja = $cajaModel->findById($cajaBancoId);
                if ($caja && $caja['cuenta_contable_id']) {
                    $cuentaBanco = $cuentaModel->findById($caja['cuenta_contable_id']);
                }
                if (!$cuentaBanco) $cuentaBanco = $cuentaModel->findByCodigo('1101');
                $cuentaDeudas = $cuentaModel->findByCodigo('2400');

                if ($cuentaBanco && $cuentaDeudas) {
                    $lineas = [];

                    // DEBE: Deudas Financieras (capital)
                    $lineas[] = [
                        'cuenta_contable_id' => $cuentaDeudas['id'],
                        'debe'               => (float)$cuota['capital'],
                        'haber'              => 0,
                    ];

                    // DEBE: Gastos Bancarios / Intereses (si hay interes)
                    if ((float)$cuota['interes'] > 0) {
                        $cuentaInteres = $cuentaModel->findByCodigo('5800');
                        if ($cuentaInteres) {
                            $lineas[] = [
                                'cuenta_contable_id' => $cuentaInteres['id'],
                                'debe'               => (float)$cuota['interes'],
                                'haber'              => 0,
                            ];
                        }
                    }

                    // HABER: Banco/Caja (monto total de la cuota)
                    $lineas[] = [
                        'cuenta_contable_id' => $cuentaBanco['id'],
                        'debe'               => 0,
                        'haber'              => (float)$cuota['monto'],
                    ];

                    $asientoModel = new AsientoContable();
                    $asientoModel->create([
                        'fecha'          => date('Y-m-d'),
                        'descripcion'    => "Pago cuota #{$cuota['numero_cuota']} credito #{$creditoId}: {$credito['entidad']}",
                        'tipo'           => 'OPERACION',
                        'origen_modulo'  => 'CREDITOS',
                        'origen_tipo'    => 'CUOTA',
                        'origen_id'      => $cuotaId,
                        'usuario_id'     => $_SESSION['user_id'],
                    ], $lineas);
                }
            } catch (Exception $e) {
                empresaLog("Error asiento cuota: " . $e->getMessage(), 'ERROR');
            }

            $_SESSION['success'] = "Cuota #{$cuota['numero_cuota']} pagada.";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . "/creditos/show/{$creditoId}");
        exit;
    }

    public function cancelar(int $id): void{
        $this->requireModule();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/creditos');
            exit;
        }

        $model = new CreditoBancario();
        $model->cancelarCredito($id);

        $_SESSION['success'] = 'Credito cancelado.';
        header('Location: ' . BASE_URL . '/creditos');
        exit;
    }
}
