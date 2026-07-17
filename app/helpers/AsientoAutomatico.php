<?php
require_once BASE_PATH . '/app/models/AsientoContable.php';
require_once BASE_PATH . '/app/models/CuentaContable.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';

/**
 * Genera asientos contables automáticos para distintos módulos.
 */
class AsientoAutomatico{

    private AsientoContable $asientoModel;
    private CuentaContable $cuentaModel;
    private CajaBanco $cajaModel;

    private static $categoriaCuentaMap = [
        'PROVEEDORES' => '5100',
        'SUELDOS'     => '5300',
        'SERVICIOS'   => '5400',
        'ALQUILER'    => '5500',
        'IMPUESTOS'   => '5600',
        'OTROS'       => '5700',
    ];

    public function __construct(){
        $this->asientoModel = new AsientoContable();
        $this->cuentaModel  = new CuentaContable();
        $this->cajaModel    = new CajaBanco();
    }

    /**
     * Genera asiento automático al pagar un gasto.
     *
     * DEBE:  Cuenta de gasto (monto_base) + IVA a Pagar (monto_impuesto)
     * HABER: Caja/Banco (monto_total)
     */
    public function gastoPagar(array $gasto, int $cajaBancoId, int $usuarioId): int{
        $cuentaGasto = $this->getCuentaGasto($gasto['categoria']);
        $cuentaCaja  = $this->getCuentaCajaBanco($cajaBancoId);

        $montoBase     = (float)($gasto['monto_base'] ?? $gasto['monto_total']);
        $montoImpuesto = (float)($gasto['monto_impuesto'] ?? 0);
        $montoTotal    = (float)$gasto['monto_total'];

        $lineas = [];

        // DEBE: Cuenta de gasto (monto base)
        $lineas[] = [
            'cuenta_contable_id' => $cuentaGasto['id'],
            'debe'               => $montoBase,
            'haber'              => 0,
        ];

        // DEBE: IVA a Pagar (si hay impuesto)
        if ($montoImpuesto > 0) {
            $cuentaIva = $this->cuentaModel->findByCodigo('2200');
            if ($cuentaIva) {
                $lineas[] = [
                    'cuenta_contable_id' => $cuentaIva['id'],
                    'debe'               => $montoImpuesto,
                    'haber'              => 0,
                ];
            }
        }

        // HABER: Caja/Banco (monto total)
        $lineas[] = [
            'cuenta_contable_id' => $cuentaCaja['id'],
            'debe'               => 0,
            'haber'              => $montoTotal,
        ];

        $asientoId = $this->asientoModel->create([
            'fecha'          => $gasto['fecha'],
            'descripcion'    => "Pago gasto #{$gasto['id']}: {$gasto['descripcion']}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'GASTOS',
            'origen_tipo'    => 'PAGO',
            'origen_id'      => $gasto['id'],
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Gasto categoría: {$gasto['categoria']}",
        ], $lineas);

        // Registrar movimiento en caja/banco
        $this->cajaModel->registrarMovimiento([
            'caja_banco_id'     => $cajaBancoId,
            'fecha'             => $gasto['fecha'],
            'tipo'              => 'EGRESO',
            'monto'             => $montoTotal,
            'descripcion'       => "Pago gasto #{$gasto['id']}: {$gasto['descripcion']}",
            'asiento_contable_id' => $asientoId,
            'referencia_modulo' => 'GASTOS',
            'referencia_tipo'   => 'PAGO',
            'referencia_id'     => $gasto['id'],
            'usuario_id'        => $usuarioId,
        ]);

        return $asientoId;
    }

    /**
     * Anula el asiento de pago de un gasto (genera asiento inverso + reversa movimiento caja).
     */
    public function gastoAnularPago(array $gasto, int $usuarioId): ?int{
        $asiento = $this->asientoModel->findByOrigen('GASTOS', 'PAGO', $gasto['id']);
        if (!$asiento) return null;

        // Anular el asiento (genera inverso)
        $nuevoAsientoId = $this->asientoModel->anular($asiento['id'], $usuarioId);

        // Buscar y reversar el movimiento de caja asociado
        $stmt = $this->cajaModel->getMovimientosByReferencia('GASTOS', 'PAGO', $gasto['id']);
        if ($stmt) {
            $this->cajaModel->registrarMovimiento([
                'caja_banco_id'     => $stmt['caja_banco_id'],
                'fecha'             => date('Y-m-d'),
                'tipo'              => 'INGRESO',
                'monto'             => (float)$stmt['monto'],
                'descripcion'       => "Reverso pago gasto #{$gasto['id']}",
                'asiento_contable_id' => $nuevoAsientoId,
                'referencia_modulo' => 'GASTOS',
                'referencia_tipo'   => 'REVERSO',
                'referencia_id'     => $gasto['id'],
                'usuario_id'        => $usuarioId,
            ]);
        }

        return $nuevoAsientoId;
    }

    /**
     * Obtiene la cuenta contable de gasto según la categoría del gasto.
     */
    private function getCuentaGasto(string $categoria): array{
        $codigo = self::$categoriaCuentaMap[$categoria] ?? '5700';
        $cuenta = $this->cuentaModel->findByCodigo($codigo);
        if (!$cuenta) {
            $cuenta = $this->cuentaModel->findByCodigo('5700');
        }
        return $cuenta;
    }

    /**
     * Obtiene la cuenta contable asociada a una caja/banco.
     */
    private function getCuentaCajaBanco(int $cajaBancoId): array{
        $caja = $this->cajaModel->findById($cajaBancoId);
        if ($caja && $caja['cuenta_contable_id']) {
            $cuenta = $this->cuentaModel->findById($caja['cuenta_contable_id']);
            if ($cuenta) return $cuenta;
        }
        // Fallback: Caja General (1101)
        $cuenta = $this->cuentaModel->findByCodigo('1101');
        return $cuenta;
    }

    // =====================================================
    // VENTAS / CTACTE CLIENTES
    // =====================================================

    /**
     * Genera asiento al registrar un DEBITO en ctacte (venta/remito).
     *
     * DEBE:  1400 Cuentas Corrientes Clientes
     * HABER: 4100 Ventas de Productos
     */
    public function ventaDebito(int $clienteId, float $monto, string $origen, int $referenciaId, int $usuarioId, ?string $clienteNombre = null): int{
        $cuentaCtacte = $this->cuentaModel->findByCodigo('1400');
        $cuentaVentas = $this->cuentaModel->findByCodigo('4100');

        $lineas = [
            [
                'cuenta_contable_id' => $cuentaCtacte['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaVentas['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ];

        $descCliente = $clienteNombre ?: "Cliente #{$clienteId}";

        return $this->asientoModel->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "Venta {$origen} #{$referenciaId} - {$descCliente}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'CTACTE',
            'origen_tipo'    => 'DEBITO',
            'origen_id'      => $referenciaId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Débito registrado: {$origen}",
        ], $lineas);
    }

    /**
     * Genera asiento al registrar un CREDITO en ctacte (pago del cliente).
     *
     * DEBE:  1101 Caja General (o la caja/banco indicada)
     * HABER: 1400 Cuentas Corrientes Clientes
     */
    public function ventaCredito(int $clienteId, float $monto, string $origen, int $referenciaId, int $usuarioId, ?int $cajaBancoId = null, ?string $clienteNombre = null): int{
        $cuentaCtacte = $this->cuentaModel->findByCodigo('1400');

        // Determinar cuenta de caja/banco
        if ($cajaBancoId) {
            $cuentaCaja = $this->getCuentaCajaBanco($cajaBancoId);
        } else {
            $cuentaCaja = $this->cuentaModel->findByCodigo('1101');
        }

        $lineas = [
            [
                'cuenta_contable_id' => $cuentaCaja['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCtacte['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ];

        $descCliente = $clienteNombre ?: "Cliente #{$clienteId}";

        $asientoId = $this->asientoModel->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "Pago {$descCliente} - {$origen}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'CTACTE',
            'origen_tipo'    => 'CREDITO',
            'origen_id'      => $referenciaId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Crédito registrado: {$origen}",
        ], $lineas);

        // Registrar ingreso en caja/banco si se indicó
        if ($cajaBancoId) {
            $this->cajaModel->registrarMovimiento([
                'caja_banco_id'       => $cajaBancoId,
                'fecha'               => date('Y-m-d'),
                'tipo'                => 'INGRESO',
                'monto'               => $monto,
                'descripcion'         => "Pago cliente #{$clienteId} - {$origen}",
                'asiento_contable_id' => $asientoId,
                'referencia_modulo'   => 'CTACTE',
                'referencia_tipo'     => 'CREDITO',
                'referencia_id'       => $referenciaId,
                'usuario_id'          => $usuarioId,
            ]);
        }

        return $asientoId;
    }
}
