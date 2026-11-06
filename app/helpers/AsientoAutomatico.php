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
     * Genera asiento al registrar una devolución de venta.
     *
     * DEBE:  4400 Devoluciones de Ventas
     * HABER: 1400 Cuentas Corrientes Clientes
     */
    public function ventaDevolucion(int $clienteId, float $monto, int $referenciaId, int $usuarioId, ?string $clienteNombre = null): int{
        $cuentaCtacte = $this->cuentaModel->findByCodigo('1400');
        $cuentaDevoluciones = $this->cuentaModel->findByCodigo('4400');

        if (!$cuentaDevoluciones) {
            $cuentaDevoluciones = $this->cuentaModel->findByCodigo('4300');
        }

        $lineas = [
            [
                'cuenta_contable_id' => $cuentaDevoluciones['id'],
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

        return $this->asientoModel->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "Devolución #{$referenciaId} - {$descCliente}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'DEVOLUCIONES',
            'origen_tipo'    => 'DEVOLUCION',
            'origen_id'      => $referenciaId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Devolución de venta registrada",
        ], $lineas);
    }

    /**
     * Genera asiento al registrar un reembolso por devolución (efectivo/transferencia).
     *
     * DEBE:  1400 Cuentas Corrientes Clientes
     * HABER: 1101 Caja General (o la caja/banco indicada)
     */
    public function ventaReembolso(int $clienteId, float $monto, int $referenciaId, int $usuarioId, ?int $cajaBancoId = null, ?string $clienteNombre = null): int{
        $cuentaCtacte = $this->cuentaModel->findByCodigo('1400');

        if ($cajaBancoId) {
            $cuentaCaja = $this->getCuentaCajaBanco($cajaBancoId);
        } else {
            $cuentaCaja = $this->cuentaModel->findByCodigo('1101');
        }

        $lineas = [
            [
                'cuenta_contable_id' => $cuentaCtacte['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCaja['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ];

        $descCliente = $clienteNombre ?: "Cliente #{$clienteId}";

        return $this->asientoModel->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "Reembolso devolución #{$referenciaId} - {$descCliente}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'DEVOLUCIONES',
            'origen_tipo'    => 'REEMBOLSO',
            'origen_id'      => $referenciaId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => "Reembolso por devolución de venta",
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

    // =====================================================
    // CHEQUES DE TERCEROS
    // =====================================================

    /**
     * Asiento al recibir un cheque: queda como activo en cartera, no como
     * disponibilidad bancaria.
     *
     * DEBE:  1350 Cheques en cartera
     * HABER: 1400 Cuentas Corrientes Clientes
     */
    public function chequeEnCartera(int $clienteId, float $monto, int $chequeId, int $usuarioId, ?string $clienteNombre = null): int{
        $cuentaCartera = $this->getCuentaChequesEnCartera();
        $cuentaCtacte = $this->cuentaModel->findByCodigo('1400');
        if (!$cuentaCtacte) {
            throw new Exception('No existe la cuenta contable 1400 (Cuentas Corrientes Clientes).');
        }

        $descCliente = $clienteNombre ?: "Cliente #{$clienteId}";

        return $this->asientoModel->create([
            'fecha'          => date('Y-m-d'),
            'descripcion'    => "Cheque en cartera #{$chequeId} - {$descCliente}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'CHEQUES',
            'origen_tipo'    => 'EN_CARTERA',
            'origen_id'      => $chequeId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => 'Cobro con cheque: el importe queda como activo en cartera, no como disponibilidad bancaria.',
        ], [
            [
                'cuenta_contable_id' => $cuentaCartera['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCtacte['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ]);
    }

    /**
     * Asiento al acreditar el cheque en una caja/banco.
     * Recien aca el cheque sale de cartera y suma a la disponibilidad real.
     *
     * DEBE:  Caja/Banco
     * HABER: 1350 Cheques en cartera
     *
     * @return array{asiento_id:int,movimiento_id:int}
     */
    public function chequeCobrado(array $cheque, int $cajaBancoId, int $usuarioId, ?string $fecha = null): array{
        $cuentaCartera = $this->getCuentaChequesEnCartera();
        $cuentaCaja = $this->getCuentaCajaBanco($cajaBancoId);
        $monto = (float)$cheque['monto'];
        $chequeId = (int)$cheque['id'];
        $fecha = $fecha ?: date('Y-m-d');
        $descripcion = "Cobro cheque {$cheque['banco']} Nº {$cheque['numero']}";

        $asientoId = $this->asientoModel->create([
            'fecha'          => $fecha,
            'descripcion'    => $descripcion,
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'CHEQUES',
            'origen_tipo'    => 'COBRADO',
            'origen_id'      => $chequeId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => 'Acreditacion del cheque en caja/banco.',
        ], [
            [
                'cuenta_contable_id' => $cuentaCaja['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCartera['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ]);

        $movimientoId = $this->cajaModel->registrarMovimiento([
            'caja_banco_id'       => $cajaBancoId,
            'fecha'               => $fecha,
            'tipo'                => 'INGRESO',
            'monto'               => $monto,
            'descripcion'         => $descripcion,
            'asiento_contable_id' => $asientoId,
            'referencia_modulo'   => 'CHEQUES',
            'referencia_tipo'     => 'COBRADO',
            'referencia_id'       => $chequeId,
            'usuario_id'          => $usuarioId,
        ]);

        return ['asiento_id' => $asientoId, 'movimiento_id' => $movimientoId];
    }

    /**
     * Asiento al endosar un cheque a un proveedor: cancela deuda sin pasar
     * por caja/banco.
     *
     * DEBE:  2100 Cuentas Corrientes Proveedores
     * HABER: 1350 Cheques en cartera
     */
    public function chequeEndosado(array $cheque, int $proveedorId, int $usuarioId, ?string $fecha = null): int{
        $cuentaCartera = $this->getCuentaChequesEnCartera();
        $cuentaProveedores = $this->cuentaModel->findByCodigo('2100');
        if (!$cuentaProveedores) {
            throw new Exception('No existe la cuenta contable 2100 (Cuentas Corrientes Proveedores).');
        }

        $monto = (float)$cheque['monto'];
        $chequeId = (int)$cheque['id'];
        $fecha = $fecha ?: date('Y-m-d');

        return $this->asientoModel->create([
            'fecha'          => $fecha,
            'descripcion'    => "Endoso cheque {$cheque['banco']} Nº {$cheque['numero']} a proveedor #{$proveedorId}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'CHEQUES',
            'origen_tipo'    => 'ENDOSADO',
            'origen_id'      => $chequeId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => 'Endoso de cheque para cancelar deuda con proveedor.',
        ], [
            [
                'cuenta_contable_id' => $cuentaProveedores['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCartera['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ]);
    }

    private function getCuentaChequesEnCartera(): array{
        $cuenta = $this->cuentaModel->findByCodigo('1350');
        if (!$cuenta) {
            throw new Exception('No existe la cuenta contable 1350 (Cheques en cartera). Ejecute la migracion 024.');
        }
        return $cuenta;
    }

    // =====================================================
    // FACTURAS DE COMPRA Y ORDENES DE PAGO
    // =====================================================

    /**
     * Asiento al registrar una factura de compra: la compra queda devengada
     * en la deuda con el proveedor, sin tocar caja.
     *
     * DEBE:  5100 Proveedores (gasto de compra) + 2200 IVA
     * HABER: 2100 Cuentas Corrientes Proveedores
     */
    public function facturaCompraRegistrada(array $factura, int $usuarioId): int{
        $cuentaGasto = $this->cuentaModel->findByCodigo('5100');
        if (!$cuentaGasto) {
            throw new Exception('No existe la cuenta contable 5100 (Proveedores).');
        }
        $cuentaProveedores = $this->cuentaModel->findByCodigo('2100');
        if (!$cuentaProveedores) {
            throw new Exception('No existe la cuenta contable 2100 (Cuentas Corrientes Proveedores).');
        }

        $montoBase  = (float)$factura['monto_base'];
        $montoIva   = (float)$factura['monto_iva'];
        $montoTotal = (float)$factura['monto_total'];
        $tipoFiscal = trim((string)($factura['tipo_fiscal'] ?? '')) !== ''
            ? trim((string)$factura['tipo_fiscal']) . ' '
            : '';

        $lineas = [
            [
                'cuenta_contable_id' => $cuentaGasto['id'],
                'debe'               => $montoBase,
                'haber'              => 0,
            ],
        ];

        if ($montoIva > 0) {
            $cuentaIva = $this->cuentaModel->findByCodigo('2200');
            if ($cuentaIva) {
                $lineas[] = [
                    'cuenta_contable_id' => $cuentaIva['id'],
                    'debe'               => $montoIva,
                    'haber'              => 0,
                ];
            }
        }

        $lineas[] = [
            'cuenta_contable_id' => $cuentaProveedores['id'],
            'debe'               => 0,
            'haber'              => $montoTotal,
        ];

        return $this->asientoModel->create([
            'fecha'          => (string)$factura['fecha_factura'],
            'descripcion'    => "Factura de compra {$tipoFiscal}Nº {$factura['numero']} - Proveedor #{$factura['proveedor_id']}",
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'FACTURAS_COMPRA',
            'origen_tipo'    => 'REGISTRO',
            'origen_id'      => (int)$factura['id'],
            'usuario_id'     => $usuarioId,
            'observaciones'  => 'Registro de factura de compra. El pago se hace por orden de pago.',
        ], $lineas);
    }

    /**
     * Asiento al pagar una orden de pago: cancela la deuda del proveedor
     * con el egreso real de caja o banco.
     *
     * DEBE:  2100 Cuentas Corrientes Proveedores
     * HABER: Caja/Banco
     *
     * @return array{asiento_id:int,movimiento_id:int}
     */
    public function ordenPagoPagada(array $orden, int $cajaBancoId, int $usuarioId, ?string $fecha = null): array{
        $cuentaProveedores = $this->cuentaModel->findByCodigo('2100');
        if (!$cuentaProveedores) {
            throw new Exception('No existe la cuenta contable 2100 (Cuentas Corrientes Proveedores).');
        }
        $cuentaCaja = $this->getCuentaCajaBanco($cajaBancoId);

        $monto = (float)$orden['monto'];
        $ordenId = (int)$orden['id'];
        $fecha = $fecha ?: date('Y-m-d');
        $descripcion = "Pago orden de pago #{$orden['numero']} - Proveedor #{$orden['proveedor_id']}";

        $asientoId = $this->asientoModel->create([
            'fecha'          => $fecha,
            'descripcion'    => $descripcion,
            'tipo'           => 'OPERACION',
            'origen_modulo'  => 'ORDENES_PAGO',
            'origen_tipo'    => 'PAGO',
            'origen_id'      => $ordenId,
            'usuario_id'     => $usuarioId,
            'observaciones'  => 'Pago de factura de compra por orden de pago.',
        ], [
            [
                'cuenta_contable_id' => $cuentaProveedores['id'],
                'debe'               => $monto,
                'haber'              => 0,
            ],
            [
                'cuenta_contable_id' => $cuentaCaja['id'],
                'debe'               => 0,
                'haber'              => $monto,
            ],
        ]);

        $movimientoId = $this->cajaModel->registrarMovimiento([
            'caja_banco_id'       => $cajaBancoId,
            'fecha'               => $fecha,
            'tipo'                => 'EGRESO',
            'monto'               => $monto,
            'descripcion'         => $descripcion,
            'asiento_contable_id' => $asientoId,
            'referencia_modulo'   => 'ORDENES_PAGO',
            'referencia_tipo'     => 'PAGO',
            'referencia_id'       => $ordenId,
            'usuario_id'          => $usuarioId,
        ]);

        return ['asiento_id' => $asientoId, 'movimiento_id' => $movimientoId];
    }
}
